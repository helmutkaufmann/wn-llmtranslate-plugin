<?= Form::open(['id' => 'machineTranslateForm']) ?>
    <input type="hidden" name="locale_from" value="<?= e($localeFrom) ?>" />
    <input type="hidden" name="locale_to" value="<?= e($localeTo) ?>" />
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="popup">&times;</button>
        <h4 class="modal-title"><?= e(trans('mercator.llmtranslate::lang.messages.title')) ?></h4>
    </div>
    <div class="modal-body">
        <?php if (!$providers): ?>
            <p class="flash-message static warning"><?= e(trans('mercator.llmtranslate::lang.messages.no_provider')) ?></p>
                <?php else: ?>
            <p class="help-block"><?= e(trans('mercator.llmtranslate::lang.messages.help', ['max' => $max])) ?></p>
            <p><strong><?= e($localeFrom ?: $defaultCode) ?> &rarr; <span id="mtTargetLabel"><?= e($localeTo ?: '?') ?></span></strong></p>
            <div class="checkbox custom-checkbox">
                <input type="checkbox" name="all_languages" value="1" id="mtAll" <?= $localeTo ? '' : 'checked' ?>
                    onchange="$('#mtTargetLabel').text(this.checked ? <?= e(json_encode(trans('mercator.llmtranslate::lang.messages.all_label'))) ?> : <?= e(json_encode($localeTo ?: '?')) ?>)">
                <label for="mtAll"><?= e(trans('mercator.llmtranslate::lang.messages.all_languages')) ?></label>
            </div>
            <div class="form-group">
                <label><?= e(trans('mercator.llmtranslate::lang.messages.provider')) ?></label>
                <select name="provider" class="form-control custom-select">
                    <?php foreach ($providers as $name): ?>
                        <option value="<?= e($name) ?>"><?= e($name) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="checkbox custom-checkbox">
                <input type="checkbox" name="overwrite" value="1" id="mtOverwrite">
                <label for="mtOverwrite"><?= e(trans('mercator.llmtranslate::lang.messages.overwrite')) ?></label>
            </div>
        <?php endif ?>
    </div>
    <div class="modal-footer">
        <?php if ($providers): ?>
        <button type="submit" class="btn btn-primary"
            data-request="onMachineTranslateMessages"
            data-request-success="$(this).trigger('close.oc.popup')"
            data-load-indicator="..."><?= e(trans('mercator.llmtranslate::lang.messages.run')) ?></button>
        <?php endif ?>
        <button type="button" class="btn btn-default" data-dismiss="popup"><?= e(trans('backend::lang.form.cancel')) ?></button>
    </div>
<?= Form::close() ?>
