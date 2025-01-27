<?php
namespace Mitisk\Yii2Admin\components;

use yii\validators\StringValidator;

class AliasValidator extends StringValidator
{
    public $name_attribute = 'name';

    /**
     * Validates a single attribute.
     * Transform value: translite cyrillic letters, remove other not-allowed letters, remove unwanted spaces.
     * @param \yii\base\Model $model the data model to be validated
     * @param string $attribute the name of the attribute to be validated.
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;

        if ((!$value && $model->hasAttribute($this->name_attribute)) ||
            (strlen(trim($value)) === 0 &&
                ($model->hasAttribute($this->name_attribute) || $model->hasProperty($this->name_attribute)))) {
            $value = $model->{$this->name_attribute};
        }

        // Применяем преобразования с использованием стрелочных функций и более точных указателей на типы
        $processValue = function($value) {
            return trim(
                preg_replace('~[^-a-z0-9_]+~u', '-',
                    strtolower($this->translit(trim($value))))
                , '-');
        };

        $model->$attribute = $processValue($value);
        parent::validateAttribute($model, $attribute);
    }

    /**
     * Translite cyrillic letters and other unwanted symbols.
     * @param string $str value for transformation
     * @return string transformed string
     */
    protected function translit($str)
    {
        $tr = array(
            "А"=>"A","Б"=>"B","В"=>"V","Г"=>"G",
            "Д"=>"D","Е"=>"E","Ж"=>"J","З"=>"Z","И"=>"I",
            "Й"=>"Y","К"=>"K","Л"=>"L","М"=>"M","Н"=>"N",
            "О"=>"O","П"=>"P","Р"=>"R","С"=>"S","Т"=>"T",
            "У"=>"U","Ф"=>"F","Х"=>"H","Ц"=>"TS","Ч"=>"CH",
            "Ш"=>"SH","Щ"=>"SCH","Ъ"=>"","Ы"=>"YI","Ь"=>"",
            "Э"=>"E","Ю"=>"YU","Я"=>"YA","а"=>"a","б"=>"b",
            "в"=>"v","г"=>"g","д"=>"d","е"=>"e","ж"=>"j",
            "з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
            "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
            "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
            "ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
            "ы"=>"yi","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya",
            "_"=>"-","."=>"-",","=>"-"," "=>"-","?"=>"-","/"=>"-","\\"=>"-",
            "*"=>"-",":"=>"-","\""=>"","<"=>"-",
            ">"=>"-","|"=>"-","«"=>"","»"=>"","+"=>"","("=>"",")"=>"","#"=>"-"
        );
        return strtr($str,$tr);
    }
}
