<?php
use \yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $models \Mitisk\Yii2Admin\models\Menu[] */

$this->title = 'Меню';
$this->params['breadcrumbs'][] = $this->title;
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




<style>
    .widget-content-tab {
        font-size: 14px;
    }
    .list-group {
        color: #111111;
        font-size: 16px!important;
        line-height: 17px;
        text-transform: capitalize;
    }
    .list-group a i {
        font-size: 14px!important;
    }
    .list-group a {
        padding: 10px;
    }
    .table-icons {
        border: 0;
        margin: 0;
        table-layout: auto;
        font-size: 16px!important;
    }
    .table-icons td {
        border: 0;
    }
    .table-icons i, .iconpicker i {
        font-size: 16px!important;
    }
    .popover-arrow {
        color: unset;
    }
</style>

<?php $this->registerJsFile('https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js', ['depends' => [\yii\web\JqueryAsset::class]]) ?>
<?php $this->registerJsFile('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js', ['depends' => [\yii\web\JqueryAsset::class]]) ?>
<?php $this->registerJsFile('/js/menu-editor/jquery-menu-editor.min.js', ['depends' => [\yii\web\JqueryAsset::class]]) ?>
<?php $this->registerJsFile('/js/bootstrap-iconpicker/js/iconset/fontawesome5-3-1.min.js', ['depends' => [\yii\web\JqueryAsset::class]]) ?>
<?php $this->registerJsFile('/js/bootstrap-iconpicker/js/bootstrap-iconpicker.min.js', ['depends' => [\yii\web\JqueryAsset::class]]) ?>

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

        $(document).on('change', '.js-change-input', function(){
            var alias = $(this).data('alias');
            var value = $(this).val();
            var name = $(this).attr('name');

            if($('#' + name + '-' + alias).length) {
                $('#' + name + '-' + alias).val($(this).val());
            } else {
                $('#form-main').append('<input type="hidden" id="' + name + '-' + alias + '" name="Menu[' + alias + '][' + name + ']" value="' + value + '" />');

                if(!$('#' + alias).length) {
                    $('#form-main').append('<input type="hidden" id="' + alias + '" name="Menu[' + alias + '][data]" value="" />');
                }

            }
        });

        /* ====================================== */

        /** PAGE ELEMENTS **/
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
