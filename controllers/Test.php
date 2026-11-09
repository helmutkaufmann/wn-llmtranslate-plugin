<?php namespace Mercator\LlmTranslate\Controllers;

use Backend\Classes\Controller;
use Mercator\LlmTranslate\Classes\LlmTranslateProvider;
use Mercator\LlmTranslate\Models\Settings;

/**
 * AJAX-only endpoint behind the "Test connection" button of the endpoint form.
 */
class Test extends Controller
{
    public $requiredPermissions = ['mercator.llmtranslate.manage_settings'];

    public function index()
    {
        return \Response::make('', 404);
    }

    public function onTest()
    {
        $ep = [
            'format'   => (string) post('format', 'openai'),
            'base_url' => (string) post('base_url'),
            'model'    => (string) post('model'),
            'api_key'  => (string) post('api_key'),
        ];

        // An unrevealed sensitive field posts a placeholder: use the saved key.
        if ($ep['api_key'] === '__hidden__') {
            $ep['api_key'] = '';
            foreach ((array) Settings::get('endpoints') as $saved) {
                if (($saved['name'] ?? null) === post('name') || ($saved['base_url'] ?? null) === $ep['base_url']) {
                    $ep['api_key'] = (string) ($saved['api_key'] ?? '');
                    break;
                }
            }
        }

        if (trim($ep['base_url']) === '' || trim($ep['model']) === '') {
            return ['ok' => false, 'message' => 'Base URL and model are required.'];
        }

        try {
            $cfg = Settings::endpointConfig($ep);
            $cfg['timeout'] = 30;
            $res = (new LlmTranslateProvider($cfg))->ping();

            return [
                'ok'      => true,
                'message' => sprintf('%s (%d ms): "%s"', $res['url'], $res['ms'], mb_substr($res['reply'], 0, 80)),
            ];
        }
        catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
