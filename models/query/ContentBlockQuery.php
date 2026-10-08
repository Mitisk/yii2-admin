<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models\query;

use Mitisk\Yii2Admin\models\ContentBlock;
use yii\db\ActiveQuery;

/**
 * @method ContentBlock|null one($db = null)
 * @method ContentBlock[] all($db = null)
 */
class ContentBlockQuery extends ActiveQuery
{
    public function active(): static
    {
        return $this->andWhere([ContentBlock::tableName() . '.[[is_active]]' => 1]);
    }

    public function byKey(string $key): static
    {
        return $this->andWhere([ContentBlock::tableName() . '.[[key]]' => $key]);
    }

    public function inGroup(?string $group): static
    {
        return $this->andFilterWhere([ContentBlock::tableName() . '.[[group]]' => $group]);
    }
}
