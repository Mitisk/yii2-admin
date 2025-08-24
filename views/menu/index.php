<?php
use \yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $models \Mitisk\Yii2Admin\models\Menu[] */

$this->title = 'Меню';
$this->params['breadcrumbs'][] = $this->title;

\Mitisk\Yii2Admin\assets\MenuFormAsset::register($this);
?>

<div class="tf-section-2 form-add-product">
    <div class="wg-box">
        <div class="widget-tabs">
            <ul class="widget-menu-tab">

                <?php foreach($models as $model): ?>
                    <li class="item-title <?= $model == $models[array_key_first($models)] ? 'active' : '' ?>">
                        <span class="inner" data-alias="<?= $model->alias ?>">
                            <h6><?= $model->name ?></h6>
                        </span>
                    </li>
                <?php endforeach; ?>

                <li class="item-title">
                    <span class="inner"><h6>+</h6></span>
                </li>

            </ul>
            <div class="widget-content-tab">
                <?php foreach($models as $model): ?>
                    <div class="widget-content-inner <?= $model == $models[array_key_first($models)] ? 'active' : '' ?>" style="<?= $model == $models[array_key_first($models)] ? '' : 'display: none;' ?>">
                        <?= $this->render('_name', ['model' => $model]) ?>
                    </div>
                <?php endforeach; ?>

                <div class="widget-content-inner" style="display: none;">
                    <?= $this->render('_name', ['model' => new \Mitisk\Yii2Admin\models\Menu()]) ?>
                </div>

            </div>
            <?= $this->render('_builder')?>
        </div>
    </div>

    <?= $this->render('_builder_form')?>

    <?= Html::beginForm('', 'post', ['id' => 'form-main']) ?>

    <?php foreach($models as $model): ?>
        <?= Html::hiddenInput('Menu[' . $model->alias . '][name]', $model->name, ['id' => 'name-' . $model->alias]) ?>
        <?= Html::hiddenInput('Menu[' . $model->alias . '][alias]', $model->alias, ['id' => 'alias-' . $model->alias]) ?>
        <?= Html::hiddenInput('Menu[' . $model->alias . '][data]', $model->data, ['id' => $model->alias]) ?>
    <?php endforeach; ?>

    <div class="bot">
        <button class="tf-button w180" type="submit" id="save-button">Сохранить</button>
    </div>

    <?= Html::endForm() ?>

</div>

<script>
    window.addEventListener('load', function() {

        var arrayjson = [];
        var active = 0;

        <?php $active = 0;?>
        <?php foreach ($models as $model): ?>

        <?php if(!$active): ?>
        <?php $active = $model->alias; ?>
        active = "<?= $model->alias ?>";
        <?php endif;?>

        arrayjson["<?= $model->alias ?>"] = <?= $model->data ? $model->data : '[]' ?>;

        <?php endforeach; ?>

        // icon picker options
        var iconPickerOptions = {searchText: "Найти...", labelHeader: "{0}/{1}"};
        // sortable list options
        var sortableListOptions = {
            placeholderCss: {'background-color': "#cccccc"}
        };

        var editor = new MenuEditor('myEditor', {maxLevel: 1, listOptions: sortableListOptions, iconPicker: iconPickerOptions});
        editor.setForm($('#frmEdit'));
        editor.setUpdateButton($('#btnUpdate'));
        editor.setData(arrayjson[active]);

        $('#btnReload').on('click', function () {
            editor.setData(arrayjson);
        });

        $('#btnOutput').on('click', function () {
            var str = editor.getString();
            $("#out").text(str);
        });

        $("#btnUpdate").click(function(e){
            e.preventDefault();
            editor.update();
            $("#" + active).val(editor.getString());
        });

        $('#btnAdd').click(function(e){
            e.preventDefault();
            editor.add();
            $("#" + active).val(editor.getString());
        });

        $(document).on('click', '#save-button', function(e){
            e.preventDefault();
            $("#" + active).val(editor.getString());
            $(this).closest('form').submit();
        });

        $('.widget-menu-tab li').click(function(e){
            e.preventDefault();

            arrayjson[active] = editor.getString();

            var alias = $(this).find('span').data('alias');

            active = alias ? alias : 'new';

            if(!arrayjson[active]) {
                editor.setData([]);
            } else {
                editor.setData(arrayjson[active]);
            }

        });

    });
</script>
