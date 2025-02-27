<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
?>

<?php
$formModel = $model->getModel();

$form = ActiveForm::begin([
    'id' => get_class($formModel),
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'inputOptions' => ['class' => ''],
        'errorOptions' => ['class' => 'col-lg-7 invalid-feedback']
    ],
    'options' => [
        'class' => 'row',
        'style' => 'row-gap: 25px',
        'enctype' => 'multipart/form-data'
    ]
]) ?>

<?php
foreach ($model->getFormFields() as $value) {
    echo $value;
}
?>


<div class="bot">
    <div class="list-box-value mb-10">
        <div>
            <?= Html::submitButton($formModel->isNewRecord
                ? Yii::t('rbac', 'Create')
                : Yii::t('rbac', 'Update role'), [
                'class' => $formModel->isNewRecord
                    ? 'tf-button w208'
                    : 'tf-button w208'
            ]) ?>
        </div>
        <div>
            <?= !$formModel->isNewRecord ? Html::a(Yii::t('rbac', 'Delete'), ['delete', 'id' => $formModel->id], [
                'class' => 'tf-button tf-button-danger w208',
                'data-confirm' => Yii::t('rbac', 'Are you sure to delete this item?'),
                'data-method' => 'post',
            ]) : '' ?>
        </div>
    </div>

</div>
<?php ActiveForm::end() ?>