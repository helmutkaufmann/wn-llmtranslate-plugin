/* Adds a "Machine translate" button to the Winter.Translate Messages toolbar. */
+function ($) { "use strict";
    function addButton() {
        var $tb = $('#messagesForm .control-table .toolbar')
        if (!$tb.length || $tb.find('.mercator-mt-button').length) return
        var lang = (document.documentElement.lang || 'en').substr(0, 2)
        var label = {de: 'Maschinell übersetzen', fr: 'Traduction automatique'}[lang] || 'Machine translate'
        $('<a href="javascript:;" class="btn oc-icon-language mercator-mt-button" data-control="popup" data-handler="onLoadMachineTranslateForm"></a>')
            .text(label).appendTo($tb)
    }
    $(document).render(addButton)
    $(document).on('ajaxUpdateComplete', addButton)
    new MutationObserver(addButton).observe(document.body, {childList: true, subtree: true})
}(window.jQuery)
