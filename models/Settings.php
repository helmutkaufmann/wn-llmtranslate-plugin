<?php namespace Mercator\LlmTranslate\Models;

use Config;
use Model;
use Str;
use Winter\Translate\Providers\TranslationProvider;
use Mercator\LlmTranslate\Classes\LlmTranslateProvider;

/**
 * Backend-configurable LLM endpoints. On boot the endpoints are pushed into
 * `winter.translate::providers.*` so Winter.Translate's ProviderFactory and
 * picker see them like any other provider.
 */
class Settings extends Model
{
    public $implement = [\System\Behaviors\SettingsModel::class];

    public $settingsCode = 'mercator_llmtranslate_settings';

    public $settingsFields = 'fields.yaml';

    protected $reserved = ['google', 'deepl'];

    public function initSettingsData()
    {
        $this->endpoints = [Config::get('mercator.llmtranslate::default_endpoint')];
    }

    /**
     * @return array<string, array> provider config keyed by provider slug
     */
    public static function providerConfigs(): array
    {
        $settings = static::instance();
        $endpoints = $settings->endpoints;
        if (!is_array($endpoints) || !$endpoints) {
            $endpoints = [Config::get('mercator.llmtranslate::default_endpoint')];
        }

        $out = [];
        foreach ($endpoints as $ep) {
            $slug = Str::slug((string) ($ep['name'] ?? ''), '_');
            if ($slug === '' || empty($ep['base_url']) || empty($ep['model'])) {
                continue;
            }
            if (in_array($slug, $settings->reserved, true) || isset($out[$slug])) {
                $slug .= '_llm' . count($out);
            }

            $out[$slug] = [
                'class'        => LlmTranslateProvider::class,
                'format'       => ($ep['format'] ?? 'openai') === 'anthropic' ? 'anthropic' : 'openai',
                'url'          => rtrim((string) $ep['base_url'], '/'),
                'model'        => (string) $ep['model'],
                // Winter.Translate hides providers without a key from the picker.
                // Local servers need none, so a placeholder keeps them listed.
                'key'          => trim((string) ($ep['api_key'] ?? '')) ?: 'none',
                'has_real_key' => trim((string) ($ep['api_key'] ?? '')) !== '',
                'batch_size'   => (int) ($ep['batch_size'] ?? 0) ?: (int) Config::get('mercator.llmtranslate::batch_size', 20),
                'timeout'      => (int) Config::get('mercator.llmtranslate::timeout', 120),
                'instructions' => (string) $settings->instructions,
                'glossary'     => (string) $settings->glossary,
            ];
        }

        return $out;
    }

    public static function applyConfigValues(): void
    {
        foreach (static::providerConfigs() as $slug => $cfg) {
            Config::set("winter.translate::providers.$slug", $cfg);
        }
    }
}
