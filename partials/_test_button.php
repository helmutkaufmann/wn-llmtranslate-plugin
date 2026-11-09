<div class="form-group span-full">
    <button
        type="button"
        class="btn btn-default oc-icon-plug"
        data-llm-test
        data-url="<?= e(Backend::url('mercator/llmtranslate/test')) ?>"
        data-label="<?= e(trans('mercator.llmtranslate::lang.settings.test')) ?>"
        data-testing="<?= e(trans('mercator.llmtranslate::lang.settings.testing')) ?>"
    ><?= e(trans('mercator.llmtranslate::lang.settings.test')) ?></button>
    <span data-llm-test-result class="m-l"></span>
</div>
