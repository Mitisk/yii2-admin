<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Yii;
use yii\base\BaseObject;
use yii\web\UrlRuleInterface;

/**
 * Правило URL страниц. Ставится первым: у сайта могут быть правила-ловушки вроде
 * `<action:\w+> => site/<action>`. Совпадает только с адресами из карты страниц
 * и редиректов, поэтому чужие маршруты не перекрывает.
 */
class PageUrlRule extends BaseObject implements UrlRuleInterface
{
    /** Сервис страниц; null — `Yii::$app->pages`. */
    public ?PageService $service = null;

    /** Момент «сейчас» для отложенной публикации; null — `time()`. */
    public ?int $now = null;

    /** @var \Closure|null `fn(): bool` — включён ли раздел; null — настройка `pages_enabled`. */
    public ?\Closure $enabledCheck = null;

    /** @var \Closure|null `fn(int $id, ?string $token): bool` — можно ли показать не-live страницу; null — `PageService::canPreview`. */
    public ?\Closure $previewCheck = null;

    public function parseRequest($manager, $request): array|false
    {
        $path = trim((string)$request->getPathInfo(), '/');
        if ($path === '' || !$this->enabled()) {
            return false;
        }
        $service = $this->service();
        $controller = $service->controllerId;
        // Регистр не различаем: /About найдёт about, а контроллер сделает 301 на каноничный адрес
        $row = $service->resolve($path) ?? $service->resolve(mb_strtolower($path));
        if ($row !== null) {
            if ($row['status'] === 'archived') {
                return false;
            }
            if ($service->isLive($row, $this->now)) {
                return [$controller . '/view', ['id' => $row['id']]];
            }
            $token = $request->getQueryParam('preview');
            $token = is_string($token) ? $token : null;
            $allowed = $this->previewCheck !== null
                ? ($this->previewCheck)($row['id'], $token)
                : $service->canPreview($row['id'], $token);
            return $allowed ? [$controller . '/view', ['id' => $row['id']]] : false;
        }
        $redirect = $service->redirects()[$path] ?? null;
        if ($redirect !== null) {
            return [$controller . '/redirect', ['to' => $redirect['to'], 'code' => $redirect['code']]];
        }
        return false;
    }

    public function createUrl($manager, $route, $params): string|false
    {
        // Дешёвая проверка первой: createUrl зовут на каждую ссылку сайта
        if (!str_ends_with($route, '/view') || $route !== $this->service()->controllerId . '/view') {
            return false;
        }
        $path = isset($params['path']) ? trim((string)$params['path'], '/') : null;
        if ($path === null && isset($params['id'])) {
            $path = $this->service()->pathById((int)$params['id']);
        }
        if ($path === null || $path === '') {
            return false;
        }
        unset($params['path'], $params['id']);
        return $path . ($params === [] ? '' : '?' . http_build_query($params));
    }

    private function enabled(): bool
    {
        return $this->enabledCheck !== null ? ($this->enabledCheck)() : $this->service()->getEnabled();
    }

    private function service(): PageService
    {
        return $this->service ??= Yii::$app->get('pages');
    }
}
