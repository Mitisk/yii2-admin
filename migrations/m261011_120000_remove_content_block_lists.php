<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Тип блока «Список» удалён: удаляем списочные блоки, их картинки и колонку `schema`,
 * которая хранила только поля пункта списка.
 */
class m261011_120000_remove_content_block_lists extends Migration
{
    private const BLOCK_CLASS = 'Mitisk\\Yii2Admin\\models\\ContentBlock';

    public function safeUp(): void
    {
        $ids = (new Query())->select('id')->from('{{%content_block}}')->where(['type' => 'list'])->column($this->db);
        if ($ids !== []) {
            // Через модель File: её beforeDelete удаляет и сам файл из хранилища
            foreach (\Mitisk\Yii2Admin\models\File::find()->where(['class_name' => self::BLOCK_CLASS, 'item_id' => $ids])->all() as $file) {
                $file->delete();
            }
            $this->delete('{{%content_block}}', ['id' => $ids]);
        }

        if ($this->db->getTableSchema('{{%content_block}}', true)?->getColumn('schema') !== null) {
            $this->dropColumn('{{%content_block}}', 'schema');
        }

        if (Yii::$app->has('cache')) {
            \yii\caching\TagDependency::invalidate(Yii::$app->cache, 'content-block');
        }
    }

    /**
     * Возвращает колонку; удалённые списочные блоки не восстанавливаются
     * (если они ещё выводятся в шаблоне, создадутся заново из кода — но тип «Список» больше не поддерживается).
     */
    public function safeDown(): void
    {
        $this->addColumn('{{%content_block}}', 'schema', $this->text()->null()->comment('JSON: поля пункта списка')->after('value'));
    }
}
