<?php $t = function ($k, $p = []) { return e(trans('mercator.llmtranslate::lang.review.' . $k, $p)); } ?>
<input type="hidden" name="page" value="<?= $page ?>" />
<input type="hidden" name="status" value="<?= e($f['status']) ?>" />

<div class="mt-top">
    <div class="mt-seg">
        <?php foreach (['machine' => 'status_machine', 'reviewed' => 'status_reviewed', 'all' => 'status_all'] as $key => $label): ?>
            <a class="<?= $f['status'] === $key ? 'on' : '' ?>" data-request="onFilter"
               data-request-data="status: '<?= $key ?>', page: 1"><?= $t($label) ?><?php if ($key === 'machine' && $unreviewed): ?><span class="n"><?= $unreviewed ?></span><?php endif ?></a>
        <?php endforeach ?>
    </div>

    <select name="kind" class="form-control custom-select" data-request="onFilter" data-request-data="page: 1">
        <option value=""><?= $t('kind_all') ?></option>
        <option value="message" <?= $f['kind'] === 'message' ? 'selected' : '' ?>><?= $t('kind_message') ?></option>
        <option value="attribute" <?= $f['kind'] === 'attribute' ? 'selected' : '' ?>><?= $t('kind_attribute') ?></option>
    </select>
    <select name="locale" class="form-control custom-select" data-request="onFilter" data-request-data="page: 1">
        <option value=""><?= $t('locale_all') ?></option>
        <?php foreach ($locales as $l): ?><option value="<?= e($l) ?>" <?= $f['locale'] === $l ? 'selected' : '' ?>><?= e(strtoupper($l)) ?></option><?php endforeach ?>
    </select>
    <select name="provider" class="form-control custom-select" data-request="onFilter" data-request-data="page: 1">
        <option value=""><?= $t('provider_all') ?></option>
        <?php foreach ($providers as $p): ?><option value="<?= e($p) ?>" <?= $f['provider'] === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach ?>
    </select>

    <span class="mt-grow"></span>
    <a href="javascript:;" class="btn btn-default oc-icon-language" data-control="popup" data-handler="onLoadTranslateModels"><?= $t('translate_models') ?></a>
    <?php if ($f['status'] === 'machine' && $total): ?>
    <a href="javascript:;" class="btn btn-success oc-icon-check" data-request="onApproveAll"
       data-request-confirm="<?= $t('approve_all_confirm', ['count' => $total]) ?>"><?= $t('approve_all') ?> (<?= $total ?>)</a>
    <?php endif ?>
    <?php if ($f['status'] === 'machine' && $total): ?>
    <a href="javascript:;" class="btn btn-danger oc-icon-trash-o" data-request="onDeleteAll"
       data-request-confirm="<?= $t('delete_all_confirm', ['count' => $total]) ?>"><?= $t('delete_all') ?></a>
    <?php endif ?>
</div>

<?php if (!$rows->count()): ?>
    <div class="mt-empty">
        <span class="mt-check oc-icon-check-circle"></span>
        <strong><?= $t('empty') ?></strong>
    </div>
<?php else: ?>
    <?php foreach ($rows as $row): $src = $row->sourceText(); $tgt = $row->targetText(); $done = !$row->isMachine(); ?>
    <div class="mt-card <?= $done ? 'reviewed' : '' ?>">
        <div class="mt-head">
            <span class="mt-lang"><?= e(strtoupper($row->source_locale ?: 'default')) ?> &rarr; <?= e(strtoupper($row->locale)) ?></span>
            <span class="mt-title" title="<?= e($row->label()) ?>"><?= e($row->label()) ?></span>
            <span class="mt-kind"><?= $t('kind_' . $row->kind) ?></span>
            <span class="mt-status">
                <?= $done ? $t('status_reviewed') . ($row->note === 'edited' ? ' · ' . $t('edited') : '') : $t('status_machine') ?>
            </span>
        </div>

        <div class="mt-body">
            <div class="mt-col">
                <div class="mt-label"><?= $t('col_source') ?></div>
                <div class="mt-src"><?= e($src) ?></div>
            </div>
            <div class="mt-col">
                <div class="mt-label"><?= $t('col_translation') ?></div>
                <?php if ($tgt === null): ?>
                    <em><?= $t('missing') ?></em>
                <?php else: ?>
                    <textarea name="text_<?= $row->id ?>" rows="<?= max(2, min(8, substr_count($tgt, "\n") + 1 + (int) (strlen($tgt) / 60))) ?>"><?= e($tgt) ?></textarea>
                <?php endif ?>
            </div>
        </div>

        <div class="mt-foot">
            <span class="mt-chip"><?= e($row->provider) ?></span>
            <?php if ($row->llm_model): ?><span class="mt-chip"><?= e($row->llm_model) ?></span><?php endif ?>
            <span><?= e(optional($row->translated_at)->format('d.m.Y H:i')) ?></span>
            <div class="mt-actions">
                <a href="javascript:;" class="btn btn-default btn-sm mt-delete oc-icon-trash-o" data-request="onDelete" data-request-data="id: <?= $row->id ?>"
                   data-request-confirm="<?= $t($tgt === null ? 'delete_record_confirm' : 'delete_confirm') ?>"><?= $t('delete') ?></a>
                <?php if ($tgt !== null): ?>
                <a href="javascript:;" class="btn btn-primary btn-sm mt-save oc-icon-save" data-request="onSaveRow" data-request-data="id: <?= $row->id ?>"><?= $t('save_approve') ?></a>
                <?php if (!$done): ?>
                <a href="javascript:;" class="btn btn-success btn-sm mt-approve oc-icon-check" data-request="onApprove" data-request-data="id: <?= $row->id ?>"><?= $t('approve') ?></a>
                <?php endif ?>
                <?php endif ?>
            </div>
        </div>
    </div>
    <?php endforeach ?>

    <?php if ($pages > 1): ?>
    <div class="mt-pager">
        <?php if ($page > 1): ?><a href="javascript:;" class="btn btn-default btn-sm" data-request="onFilter" data-request-data="page: <?= $page - 1 ?>">&laquo;</a><?php endif ?>
        <?= $page ?> / <?= $pages ?> &middot; <?= $total ?>
        <?php if ($page < $pages): ?><a href="javascript:;" class="btn btn-default btn-sm" data-request="onFilter" data-request-data="page: <?= $page + 1 ?>">&raquo;</a><?php endif ?>
    </div>
    <?php endif ?>
<?php endif ?>
