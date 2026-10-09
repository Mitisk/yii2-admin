<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use yii\db\ActiveRecord;

/**
 * Редирект старого адреса страницы на новый.
 *
 * @property int         $id
 * @property string      $from_path
 * @property string      $to_path
 * @property int         $code
 * @property int|null    $page_id   Null — ручной редирект (задел под раздел «Редиректы»)
 * @property int         $hits
 * @property int|null    $last_hit_at
 * @property int         $created_at
 */
class PageRedirect extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%page_redirect}}';
    }

    public function rules(): array
    {
        return [
            [['from_path', 'to_path'], 'required'],
            [['from_path', 'to_path'], 'string', 'max' => 512],
            ['from_path', 'unique'],
            ['code', 'in', 'range' => [301, 302]],
            ['code', 'default', 'value' => 301],
            [['page_id', 'hits', 'last_hit_at'], 'integer'],
        ];
    }

    /**
     * Записать редирект `from → to`; существующий с таким `from` перезаписывается,
     * редирект «сам на себя» не создаётся, цепочки a→b, b→c схлопываются в a→c.
     */
    public static function upsert(string $from, string $to, ?int $pageId): void
    {
        $from = trim($from, '/');
        $to = trim($to, '/');
        if ($from === '' || $from === $to) {
            return;
        }
        $model = static::findOne(['from_path' => $from]) ?? new static(['from_path' => $from, 'created_at' => time()]);
        $model->to_path = $to;
        $model->page_id = $pageId;
        $model->code = 301;
        $model->save(false);
        static::updateAll(['to_path' => $to], ['to_path' => $from]);
        // После схлопывания цепочки могли появиться редиректы «сам на себя» — убираем
        static::deleteAll('[[from_path]] = [[to_path]]');
    }
}
