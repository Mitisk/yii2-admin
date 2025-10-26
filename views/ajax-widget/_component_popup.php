<?php
/* @var $components array */
?>
<div class="modal fade" id="component-picker-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Выберите компонент</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" id="component-picker-close">×</button>
            </div>

            <div class="modal-body">
                <?php if (!empty($components)): ?>
                    <form id="component-picker-form">
                        <div class="form-group">
                            <select id="component-select" name="alias" class="tom-select">
                                <option value="">— выберите —</option>
                                <?php foreach ($components as $c): ?>
                                    <option value="<?= htmlspecialchars($c['alias']) ?>">
                                        <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['alias']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php else: ?>
                    <div>Нет доступных компонентов</div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="tf-button style-2 tf-info" data-dismiss="modal" id="component-picker-cancel">Отмена</button>
                <button type="button" class="tf-button w208" id="component-picker-apply">Выбрать</button>
            </div>

        </div>
    </div>
</div>