<?php

/** @var yii\web\View $this */
/** @var string $key */

use yii\helpers\Json;

$this->title = 'Сохранено';
$this->registerJs(
    'window.parent.postMessage(' . Json::htmlEncode(['type' => 'ab-block-saved', 'key' => $key]) . ', window.location.origin);',
    \yii\web\View::POS_END
);
?>
<div class="block-warning type-main w-full"><div class="body-title-2">Сохранено</div></div>
