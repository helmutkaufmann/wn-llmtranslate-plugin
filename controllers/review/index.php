<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('system/settings') ?>"><?= e(trans('system::lang.settings.menu_label')) ?></a></li>
        <li><?= e(trans($this->pageTitle)) ?></li>
    </ul>
<?php Block::endPut() ?>

<div class="padded-container">
    <?= Form::open(['id' => 'reviewForm', 'onsubmit' => 'return false']) ?>
        <div id="reviewContainer"><?= $this->makePartial('list') ?></div>
    <?= Form::close() ?>
</div>
