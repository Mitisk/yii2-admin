<?php
namespace Mitisk\Yii2Admin\fields;

use yii\base\BaseObject;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

class FieldsHelper extends BaseObject
{
    /**
     * Возвращает тип поля от его названия для formBuilder
     * @param string $name Название поля
     * @return string
     */
    public static function getFieldsTypeByName(string $name) : string
    {
        switch ($name) {
            case 'created_at':
            case 'updated_at':
            case 'deleted_at':
            case 'date':
            case 'time':
            case 'datetime':
                return 'date';
                break;
            case 'text':
            case 'data':
            case 'json':
            case 'html':
            case 'textarea':
                return 'textarea';
                break;
            case 'file':
            case 'image':
            case 'files':
            case 'images':
                return 'file';
                break;
            case 'published':
            case 'active':
                return 'checkbox-group';
                break;
            default:
                return 'text';
                break;
        }
    }

    /**
     * Возвращает массив колонок из строки
     * @param string|null $string Строка с колонками
     * @return string
     */
    public static function getColumns(string|null $string = '') : string
    {
        if ($string) {
            // Регулярное выражение для поиска маски col-md-99, где 99 - любое число
            $pattern = '/col-md-(\d+)/';

            // Используем preg_match_all для поиска всех соответствий в строке
            preg_match_all($pattern, $string, $matches);

            // Возвращаем найденные значения
            return is_array(ArrayHelper::getValue($matches, 0)) ? implode(' ', ArrayHelper::getValue($matches, 0)) : ArrayHelper::getValue($matches, 0);
        }

        return '';
    }

    /**
     * Возвращает массив значений
     * @return array
     */
    public static function getValues($field): array
    {
        $values = [];

        if ($field->publicStaticMethod) {
            $reflectionClass = $field->model->getReflectionClass();
            $reflectionMethod = $reflectionClass->getMethod($field->publicStaticMethod);

            if ($reflectionMethod) {
                // Создаем экземпляр модели
                $modelInstance = new $reflectionClass->name();

                // Вызываем метод (статический или нестатический)
                $result = $reflectionMethod->isStatic()
                    ? $reflectionMethod->invoke(null) // Статический метод
                    : $reflectionMethod->invoke($modelInstance); // Нестатический метод

                // Проверяем, является ли результат ActiveQuery
                if ($result instanceof ActiveQuery) {
                    $query = clone $result;
                    $query->primaryModel = null;
                    $query->link = null;
                    $query->via = null;

                    // Получаем данные из базы данных
                    $queryResult = $query->all();

                    // Преобразуем результат в массив ключ-значение
                    foreach ($queryResult as $item) {
                        $values[$item->id] = ArrayHelper::getValue($item, 'name'); // Заменить 'id' и 'name' на соответствующие поля
                    }

                } elseif (is_array($result)) {
                    // Если результат уже массив, используем его напрямую
                    $values = $result;
                }
            }
        }

        // Если значения не были получены через метод, используем field->values
        if (!$values && $field->values) {
            foreach ($field->values as $key => $value) {
                $values[ArrayHelper::getValue($value, 'value')] = ArrayHelper::getValue($value, 'label');
            }
        }

        // Добавляем пустое значение, если поле необязательное
        if ($field instanceof SelectField) {
            if (!$field->required && $values) {
                $values = array_merge([null => '---'], $values);
            }
        }

        return $values;
    }
}
