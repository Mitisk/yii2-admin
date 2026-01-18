<?php
namespace Mitisk\Yii2Admin\fields;

use Mitisk\Yii2Admin\models\File;
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
                return 'posted';
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
                $values = [null => '---'] + $values;
            }
        }

        return $values;
    }

    /**
     * Проверка на картинку
     * @param string|null $localPath Локальный путь
     * @param string $publicPath Публичный путь
     * @return bool true если картинка
     */
    public static function isImageFile(?string $localPath, string $publicPath): bool
    {
        // Белый список расширений как последний fallback
        $ext = strtolower(pathinfo(parse_url($publicPath, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $extIsImage = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg'], true);

        // 1) exif_imagetype по локальному пути
        if ($localPath && @is_file($localPath) && function_exists('exif_imagetype')) {
            $t = @exif_imagetype($localPath);
            if ($t !== false) {
                return true;
            }
        }

        // 2) finfo_file по локальному пути
        if ($localPath && @is_file($localPath) && class_exists('finfo')) {
            $f = new \finfo(FILEINFO_MIME);
            $mimeFull = @$f->file($localPath); // например "image/jpeg; charset=binary"
            if ($mimeFull) {
                $mime = strtok($mimeFull, ';');
                if (strpos($mime, 'image/') === 0) {
                    return true;
                }
            }
        }

        // 3) getimagesize по локальному пути
        if ($localPath && @is_file($localPath) && function_exists('getimagesize')) {
            $info = @getimagesize($localPath);
            if (is_array($info) && !empty($info['mime']) && strpos($info['mime'], 'image/') === 0) {
                return true;
            }
        }

        // 4) Fallback: по расширению (чтобы “вернуть” картинки в интерфейсе)
        return $extIsImage;
    }

    public static function getFiles($model, $field)
    {
        return File::find()->where([
            'class_name' => get_class($model),
            'item_id' => $model->id,
            'field_name' => $field,
        ])->all();
    }
}
