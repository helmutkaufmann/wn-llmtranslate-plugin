/* Review screen: flag edited cards so the primary action becomes "Save". */
+function ($) { "use strict";
    $(document).on('input', '.mt-review .mt-col textarea', function () {
        var $card = $(this).closest('.mt-card')
        $card.toggleClass('is-dirty', this.value !== this.defaultValue)
    })
    // Ctrl/Cmd+Enter in a textarea saves it
    $(document).on('keydown', '.mt-review .mt-col textarea', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            $(this).closest('.mt-card').find('.mt-save:visible, .mt-approve:visible').first().click()
        }
    })
}(window.jQuery)
