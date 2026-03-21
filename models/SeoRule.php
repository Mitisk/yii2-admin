<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * Модель для таблицы {{%seo_rules}}.
 *
 * @property int         $id
 * @property string      $pattern
 * @property string|null $title
 * @property string|null $description
 * @property string|null $keywords
 * @property int         $priority
 * @property bool        $is_active
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property string|null $robots
 */
class SeoRule extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%seo_rules}}';
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios['search'] = ['pattern', 'title', 'is_active'];
        return $scenarios;
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['pattern'], 'required', 'except' => 'search'],
            [['pattern'], 'string', 'max' => 255],
            [['pattern'], 'validateRegex'],
            [['title', 'keywords', 'og_title', 'og_image', 'robots'], 'string', 'max' => 255],
            [['description', 'og_description'], 'string'],
            [['priority'], 'integer'],
            [['priority'], 'default', 'value' => 0],
            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => true],
        ];
    }

    /**
     * Валидация паттерна как корректного регулярного выражения.
     */
    public function validateRegex(string $attribute): void
    {
        $pattern = $this->$attribute;
        if (empty($pattern)) {
            return;
        }

        $delimiter = mb_substr($pattern, 0, 1);
        if (!in_array($delimiter, ['/', '#', '~', '@'], true)) {
            $pattern = '#' . str_replace('#', '\#', $pattern) . '#iu';
        }

        if (@preg_match($pattern, '') === false) {
            $this->addError($attribute, 'Некорректное регулярное выражение.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'pattern' => 'URL паттерн',
            'title' => 'Title',
            'description' => 'Description',
            'keywords' => 'Keywords',
            'priority' => 'Приоритет',
            'is_active' => 'Активно',
            'og_title' => 'OG Title',
            'og_description' => 'OG Description',
            'og_image' => 'OG Image',
            'robots' => 'Robots',
        ];
    }

    /**
     * Поиск и фильтрация для GridView.
     *
     * @param array $params параметры запроса
     * @return ActiveDataProvider
     */
    public function search(array $params): ActiveDataProvider
    {
        $this->scenario = 'search';
        $query = self::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['priority' => SORT_DESC, 'id' => SORT_ASC],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere(['is_active' => $this->is_active]);
        $query->andFilterWhere(['like', 'pattern', $this->pattern]);
        $query->andFilterWhere(['like', 'title', $this->title]);

        return $dataProvider;
    }
}
