<?php $t = function ($k, $p = []) { return e(trans('mercator.llmtranslate::lang.review.' . $k, $p)); } ?>
<input type="hidden" name="page" value="<?= $page ?>" />

<div class="form-inline" style="margin-bottom:15px; display:flex; gap:8px; flex-wrap:wrap; align-items:center">
    <select name="status" class="form-control custom-select" style="width:auto" data-request="onFilter" data-request-data="page: 1">
        <option value="machine" <?= $f['status'] === 'machine' ? 'selected' : '' ?>><?= $t('status_machine') ?> (<?= $unreviewed ?>)</option>
        <option value="reviewed" <?= $f['status'] === 'reviewed' ? 'selected' : '' ?>><?= $t('status_reviewed') ?></option>
        <option value="all" <?= $f['status'] === 'all' ? 'selected' : '' ?>><?= $t('status_all') ?></option>
    </select>
    <select name="kind" class="form-control custom-select" style="width:auto" data-request="onFilter" data-request-data="page: 1">
        <option value=""><?= $t('kind_all') ?></option>
        <option value="message" <?= $f['kind'] === 'message' ? 'selected' : '' ?>><?= $t('kind_message') ?></option>
        <option value="attribute" <?= $f['kind'] === 'attribute' ? 'selected' : '' ?>><?= $t('kind_attribute') ?></option>
    </select>
    <select name="locale" class="form-control custom-select" style="width:auto" data-request="onFilter" data-request-data="page: 1">
        <option value=""><?= $t('locale_all') ?></option>
        <?php foreach ($locales as $l): ?><option value="<?= e($l) ?>" <?= $f['locale'] === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
    </select>
    <select name="provider" class="form-control custom-select" style="width:auto" data-request="onFilter" data-request-data="page: 1">
        <option value=""><?= $t('provider_all') ?></option>
        <?php foreach ($providers as $p): ?><option value="<?= e($p) ?>" <?= $f['provider'] === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach ?>
    </select>
    <span style="flex:1"></span>
    <a href="javascript:;" class="btn btn-default oc-icon-language" data-control="popup" data-handler="onLoadTranslateModels"><?= $t('translate_models') ?></a>
    <?php if ($f['status'] === 'machine' && $total): ?>
    <a href="javascript:;" class="btn btn-success oc-icon-check" data-request="onApproveAll"
       data-request-confirm="<?= $t('approve_all_confirm', ['count' => $total]) ?>"><?= $t('approve_all') ?> (<?= $total ?>)</a>
    <?php endif ?>
</div>

<?php if (!$rows->count()): ?>
    <p class="flash-message static info"><?= $t('empty') ?></p>
<?php else: ?>
<table class="table data" style="width:100%">
    <thead><tr>
        <th><?= $t('col_item') ?></th><th><?= $t('col_source') ?></th><th><?= $t('col_translation') ?></th><th><?= $t('col_by') ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $row): $src = $row->sourceText(); $tgt = $row->targetText(); ?>
        <tr>
            <td style="white-space:nowrap">
                <strong><?= e($row->label()) ?></strong><br>
                <small><?= e($row->kind) ?> &rarr; <?= e($row->locale) ?></small>
            </td>
            <td style="max-width:300px"><?= e($src) ?></td>
            <td style="min-width:300px">
                <?php if ($tgt === null): ?>
                    <em><?= $t('missing') ?></em>
                <?php else: ?>
                    <textarea name="text_<?= $row->id ?>" class="form-control" rows="2"><?= e($tgt) ?></textarea>
                <?php endif ?>
            </td>
            <td><small><?= e($row->provider) ?><?= $row->llm_model ? '<br>' . e($row->llm_model) : '' ?>
                <br><?= e(optional($row->translated_at)->format('Y-m-d H:i')) ?>
                <?php if (!$row->isMachine()): ?><br><span class="text-success"><?= $t('status_reviewed') ?><?= $row->note === 'edited' ? ' (' . $t('edited') . ')' : '' ?></span><?php endif ?></small></td>
            <td style="white-space:nowrap">
                <?php if ($tgt !== null): ?>
                <a href="javascript:;" class="btn btn-default btn-sm oc-icon-save" data-request="onSaveRow" data-request-data="id: <?= $row->id ?>"><?= $t('save') ?></a>
                <?php endif ?>
                <?php if ($row->isMachine()): ?>
                <a href="javascript:;" class="btn btn-success btn-sm oc-icon-check" data-request="onApprove" data-request-data="id: <?= $row->id ?>"><?= $t('approve') ?></a>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>

<?php if ($pages > 1): ?>
<p style="text-align:center">
    <?php if ($page > 1): ?><a href="javascript:;" class="btn btn-default btn-sm" data-request="onFilter" data-request-data="page: <?= $page - 1 ?>">&laquo;</a><?php endif ?>
    <?= $page ?> / <?= $pages ?>
    <?php if ($page < $pages): ?><a href="javascript:;" class="btn btn-default btn-sm" data-request="onFilter" data-request-data="page: <?= $page + 1 ?>">&raquo;</a><?php endif ?>
</p>
<?php endif ?>
<?php endif ?>
