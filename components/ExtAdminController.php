<?php
namespace Mitisk\Yii2Admin\components;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * Внешний контроллер админки
 * Нужно унаследовать от него для работы с админкой
 */
abstract class ExtAdminController extends Controller
{
    /**
     * Кто открывает экраны контроллера: роль или разрешение, либо список (достаточно одного).
     * По умолчанию — роль admin (и всё, что её наследует, например superAdminRole).
     * '@' — любой, кто вошёл в админку (так было до версии 1.8.0).
     * Можно задать в config строки admin_controller_map: {"access": "viewReports"}.
     *
     * @var string|string[]
     */
    public $access = 'admin';

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => fn (): bool => $this->hasAccess(),
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'view' => ['GET'],
                    'create' => ['GET','POST'],
                    'update' => ['GET','POST','PUT','PATCH'],
                    'delete' => ['POST','DELETE'],
                ],
            ],
        ];
    }

    /**
     * Есть ли у вошедшего пользователя доступ к экранам контроллера ($access).
     */
    protected function hasAccess(): bool
    {
        foreach ((array)$this->access as $item) {
            if ($item === '@' || Yii::$app->user->can($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Локальный адрес возврата из GET/POST `return`; чужой или небезопасный — $fallback.
     * Принимается только путь сайта: начинается с одного «/», без «\» и управляющих символов.
     */
    protected function safeReturnUrl(string $fallback): string
    {
        $request = Yii::$app->request;
        $return = $request->post('return', $request->get('return'));

        if (is_string($return)
            && str_starts_with($return, '/')
            && !str_starts_with($return, '//')
            && !str_contains($return, '\\')
            && !preg_match('/[\x00-\x1f\x7f]/', $return)
        ) {
            return $return;
        }

        return $fallback;
    }

    /**
     * Редирект на `return` из запроса, если это локальный путь; иначе — на $fallback.
     */
    protected function redirectBack(string $fallback): Response
    {
        return $this->redirect($this->safeReturnUrl($fallback));
    }

    public function getViewPath(): string
    {
        // Для контроллеров из пространства имён приложения — старый путь
        if (strpos(static::class, 'app\\controllers\\') === 0) {
            return Yii::getAlias('@app/views/' . $this->id);
        }
        // Для контроллеров внутри модуля — стандартное поведение модуля
        return parent::getViewPath();
    }
}
