/* Live progress bar for the "Machine translate" dialogs (messages + model fields).
 * The long-running request translates batch by batch and records progress in the
 * cache; this polls it once a second so the dialog never looks stalled. */
+function ($) { "use strict";
    var timer = null, tick = null

    function stop() { clearInterval(timer); clearInterval(tick); timer = tick = null }

    function fmt(sec) {
        var m = Math.floor(sec / 60), s = sec % 60
        return m + ':' + (s < 10 ? '0' : '') + s
    }

    $(document).on('ajaxPromise', '[data-mt-run]', function () {
        var $btn = $(this), $form = $btn.closest('form'), $box = $form.find('.mt-progress'),
            $bar = $box.find('.mt-progress-bar'), $txt = $box.find('.mt-progress-text'),
            token = $form.find('[name=progress_token]').val(),
            t = $box.data(), start = Date.now(), p = {total: 0, done: 0, label: ''}

        stop()
        $form.find('.modal-body input, .modal-body select').prop('readonly', true)
        $box.show().addClass('is-waiting')

        function render() {
            var elapsed = fmt(Math.round((Date.now() - start) / 1000)), text
            if (p.total > 0) {
                text = p.done + ' / ' + p.total + ' ' + (t.done || 'translated')
                    + (p.label ? ' (' + (t.into || 'into') + ' ' + p.label + ')' : '')
            } else {
                text = t.wait || 'Waiting for the model...'
            }
            $txt.text(text + ' · ' + elapsed + (t.keep ? ' · ' + t.keep : ''))
        }

        tick = setInterval(render, 1000)
        render()
        timer = setInterval(function () {
            $.request('onMtProgress', {
                data: {progress_token: token}, loading: false, flash: false,
                handleErrorMessage: function () {}, handleFlashMessage: function () {},
                success: function (res) {
                    p = res || p
                    if (p.total > 0) {
                        $box.removeClass('is-waiting')
                        $bar.css('width', Math.min(100, p.done / p.total * 100) + '%')
                    }
                    render()
                }
            })
        }, 1000)
    })

    $(document).on('ajaxAlways', '[data-mt-run]', stop)
}(window.jQuery)
