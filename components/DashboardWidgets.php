<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminWidget;
use Yii;
use yii\base\Widget;
use yii\helpers\Inflector;

/**
 * Верхний ряд главной: какие виджеты видит пользователь и какие он может добавить.
 *
 * Записи admin_widget с user_id = NULL — зарегистрированные виджеты («шаблоны»), доступные всем.
 * Запись с user_id — личная: порядок и «скрыт / показан». Добавить на главную можно только
 * зарегистрированный виджет; произвольный класс по имени — только супер-администратор, и только
 * наследника yii\base\Widget (иначе по имени из запроса создавался бы любой класс проекта).
 */
final class DashboardWidgets
{
    /**
     * Классы виджетов на главной пользователя, в порядке вывода.
     *
     * @return string[]
     */
    public static function visible(int $userId): array
    {
        $own = AdminWidget::find()
            ->select(['class', 'published'])
            ->where(['user_id' => $userId])
            ->orderBy(['ordering' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        $ownSet = [];
        $ownVisible = [];
        foreach ($own as $row) {
            $ownSet[self::normalize($row['class'])] = true;
            if ((int) $row['published'] === 1) {
                $ownVisible[] = $row['class'];
            }
        }

        // Сначала общие виджеты, которых пользователь ещё не трогал, затем его собственные по порядку
        $result = [];
        foreach (self::templates() as $class) {
            if (!isset($ownSet[self::normalize($class)])) {
                $result[] = $class;
            }
        }

        return array_values(array_filter(array_merge($result, $ownVisible), [self::class, 'isWidget']));
    }

    /**
     * Виджеты, которые можно добавить: зарегистрированные и скрытые пользователем, которых сейчас нет на главной.
     *
     * @return array<int, array{class: string, alias: string, title: string}>
     */
    public static function addable(int $userId): array
    {
        $visible = [];
        foreach (self::visible($userId) as $class) {
            $visible[self::normalize($class)] = true;
        }

        $hidden = AdminWidget::find()
            ->select('class')
            ->where(['user_id' => $userId, 'published' => 0])
            ->orderBy(['ordering' => SORT_ASC, 'id' => SORT_ASC])
            ->column();

        $result = [];
        foreach (array_merge(self::templates(), $hidden) as $class) {
            $key = self::normalize($class);
            if (isset($visible[$key]) || isset($result[$key]) || !self::isWidget($class)) {
                continue;
            }
            $result[$key] = [
                'class' => $key,
                'alias' => self::alias($class),
                'title' => self::title($class),
            ];
        }

        return array_values($result);
    }

    /**
     * Показывает виджет на главной пользователя: возвращает скрытый или создаёт личную запись.
     *
     * @throws \DomainException класс нельзя добавить (текст — для пользователя)
     */
    public static function add(int $userId, string $class, bool $anyClass = false): AdminWidget
    {
        $class = self::normalize($class);
        if ($class === '' || !self::isWidget($class)) {
            throw new \DomainException('Такого виджета нет.');
        }

        $alias = self::alias($class);
        $row = AdminWidget::findOne(['user_id' => $userId, 'alias' => $alias]);
        if ($row === null && !$anyClass && !self::isRegistered($class)) {
            throw new \DomainException('Этот виджет не подключён к админке. Добавить его может супер-администратор.');
        }

        if ($row === null) {
            $row = new AdminWidget();
            $row->alias = $alias;
            $row->class = '\\' . $class;
            $row->user_id = $userId;
        }
        $row->published = 1;
        if (!$row->save()) {
            throw new \DomainException('Не удалось сохранить виджет: ' . implode(', ', $row->getFirstErrors()));
        }

        return $row;
    }

    /**
     * Название виджета для списка «Добавить виджет»: статический метод dashboardTitle() класса,
     * иначе — имя класса словами.
     */
    public static function title(string $class): string
    {
        $class = self::normalize($class);
        if (method_exists($class, 'dashboardTitle')) {
            $title = (string) $class::dashboardTitle();
            if ($title !== '') {
                return $title;
            }
        }

        return Inflector::camel2words(preg_replace('/Widget$/', '', self::alias($class)) ?: self::alias($class));
    }

    public static function isWidget(string $class): bool
    {
        $class = self::normalize($class);

        return $class !== '' && class_exists($class) && is_subclass_of($class, Widget::class);
    }

    public static function isRegistered(string $class): bool
    {
        $class = self::normalize($class);
        foreach (self::templates() as $template) {
            if (self::normalize($template) === $class) {
                return true;
            }
        }

        return false;
    }

    public static function alias(string $class): string
    {
        $parts = explode('\\', self::normalize($class));

        return (string) end($parts);
    }

    /**
     * @return string[]
     */
    private static function templates(): array
    {
        return AdminWidget::find()
            ->select('class')
            ->where(['user_id' => null, 'published' => 1])
            ->orderBy(['ordering' => SORT_ASC, 'id' => SORT_ASC])
            ->column();
    }

    private static function normalize(string $class): string
    {
        return ltrim(trim($class), '\\');
    }
}
