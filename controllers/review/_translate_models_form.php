<?php $t = function ($k, $p = []) { return e(trans('mercator.llmtranslate::lang.' . $k, $p)); } ?>
<?= Form::open(['id' => 'translateModelsForm']) ?>
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="popup">&times;</button>
        <h4 class="modal-title"><?= $t('models.title') ?></h4>
    </div>
    <div class="modal-body">
    <?php if (!$providers): ?>
        <p class="flash-message static warning"><?= $t('messages.no_provider') ?></p>
    <?php elseif (!$models): ?>
        <p class="flash-message static warning"><?= $t('models.none_configured') ?></p>
    <?php else: ?>
        <p class="help-block"><?= $t('models.help', ['max' => $max]) ?></p>
        <div class="form-group">
            <label><?= $t('models.model') ?></label>
            <select name="model_class" class="form-control custom-select">
                <?php foreach ($models as $m): ?><option value="<?= e($m) ?>"><?= e($m) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= $t('models.from') ?></label>
            <select name="locale_from" class="form-control custom-select">
                <option value=""><?= e($default) ?> (<?= $t('models.default') ?>)</option>
                <?php foreach ($locales as $l): if ($l->code === $default) continue ?><option value="<?= e($l->code) ?>"><?= e($l->name) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= $t('models.to') ?></label>
            <select name="locale_to" class="form-control custom-select">
                <?php foreach ($locales as $l): if ($l->code === $default) continue ?><option value="<?= e($l->code) ?>"><?= e($l->name) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="checkbox custom-checkbox">
            <input type="checkbox" name="all_languages" value="1" id="mtmAll" checked>
            <label for="mtmAll"><?= $t('messages.all_languages') ?></label>
        </div>
        <div class="form-group">
            <label><?= $t('messages.provider') ?></label>
            <select name="provider" class="form-control custom-select">
                <?php foreach ($providers as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="checkbox custom-checkbox">
            <input type="checkbox" name="overwrite" value="1" id="mtmOverwrite">
            <label for="mtmOverwrite"><?= $t('messages.overwrite') ?></label>
        </div>
    <?php endif ?>
    </div>
    <div class="modal-footer">
        <?php if ($providers && $models): ?>
        <button type="submit" class="btn btn-primary" data-request="onTranslateModels"
            data-request-success="$(this).trigger('close.oc.popup')" data-load-indicator="..."><?= $t('messages.run') ?></button>
        <?php endif ?>
        <button type="button" class="btn btn-default" data-dismiss="popup"><?= e(trans('backend::lang.form.cancel')) ?></button>
    </div>
<?= Form::close() ?>
