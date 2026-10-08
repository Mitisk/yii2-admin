<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use yii\data\ActiveDataProvider;

/**
 * Фильтр списка блоков. Поле `key` ищет и по ключу, и по названию.
 */
class ContentBlockSearch extends ContentBlock
{
    public function rules(): array
    {
        return [
            [['key', 'name', 'group', 'type'], 'string'],
            ['is_active', 'boolean'],
        ];
    }

    public function behaviors(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = ContentBlock::find()->with('updater');
        $provider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['group' => SORT_ASC, 'key' => SORT_ASC]],
            'pagination' => ['pageSize' => 50],
        ]);
        $this->load($params);
        if (!$this->validate()) {
            $query->where('0=1');
            return $provider;
        }
        $query->inGroup($this->group !== '' ? $this->group : null)
            ->andFilterWhere(['type' => $this->type, 'is_active' => $this->is_active])
            ->andFilterWhere(['or', ['like', 'key', $this->key], ['like', 'name', $this->key]]);
        return $provider;
    }

    /**
     * Группы для фильтра.
     *
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        $groups = ContentBlock::find()->select('group')->distinct()->orderBy('group')->column();
        return array_combine($groups, $groups) ?: [];
    }
}
