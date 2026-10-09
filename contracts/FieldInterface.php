<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\contracts;

/**
 * Контракт поля админки.
 *
 * Описывает минимальный набор операций, которые модуль ожидает от любого
 * поля: рендеринг в форме, детальном виде и списке, пакетную предзагрузку
 * данных для списка (анти-N+1) и сохранение/удаление связанных данных.
 *
 * Базовый класс {@see \Mitisk\Yii2Admin\fields\Field} реализует контракт;
 * конкретные типы полей наследуются от него и переопределяют нужные методы.
 */
interface FieldInterface
{
    /**
     * Рендерит поле в форме редактирования.
     *
     * @return string
     */
    public function renderField(): string;

    /**
     * Рендерит поле в детальном виде записи.
     *
     * @return string
     */
    public function renderView(): string;

    /**
     * Возвращает конфигурацию колонки для GridView.
     *
     * @param string $column Имя атрибута/колонки
     *
     * @return array
     */
    public function renderList(string $column): array;

    /**
     * Пакетно предзагружает данные колонки для набора моделей (анти-N+1).
     *
     * @param array $models Модели текущей страницы списка
     *
     * @return void
     */
    public function preloadList(array $models): void;

    /**
     * Сохраняет связанные с полем данные (файлы, связи и т.п.).
     *
     * @return bool
     */
    public function save(): bool;

    /**
     * Удаляет связанные с полем данные.
     *
     * @return bool
     */
    public function delete(): bool;
}
