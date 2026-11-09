/* "Test connection" button inside each LLM endpoint repeater item. */
+function ($) { "use strict";
    $(document).on('click', '[data-llm-test]', function () {
        var $btn = $(this),
            $item = $btn.closest('.field-repeater-item'),
            $out = $btn.siblings('[data-llm-test-result]'),
            data = {}

        $.each(['name', 'format', 'base_url', 'model', 'api_key'], function (i, k) {
            data[k] = $item.find('[name$="[' + k + ']"]').val() || ''
        })

        $btn.prop('disabled', true).text($btn.data('testing'))
        $out.removeClass('text-success text-danger').text('')

        $btn.request('onTest', {
            url: $btn.data('url'),
            data: data,
            success: function (res) {
                $out.addClass(res.ok ? 'text-success' : 'text-danger')
                    .text((res.ok ? '✓ ' : '✗ ') + res.message)
            },
            error: function (xhr) {
                $out.addClass('text-danger').text('✗ ' + (xhr.responseText || xhr.statusText))
            },
            complete: function () {
                $btn.prop('disabled', false).text($btn.data('label'))
            }
        })
    })
}(window.jQuery)
