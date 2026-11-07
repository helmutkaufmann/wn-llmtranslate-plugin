<?php namespace Mercator\LlmTranslate\Models;

use Config;
use Str;
use Winter\Translate\Models\Setting as TranslateSetting;
use Mercator\LlmTranslate\Classes\LlmTranslateProvider;

/**
 * LLM endpoint settings. Stored on Winter.Translate's own settings record
 * (Settings -> Translation Providers) under `llm_*` keys; the form tab is
 * injected by Plugin::extendProviderSettingsForm().
 *
 * On boot the endpoints are pushed into `winter.translate::providers.*` so
 * Winter.Translate's ProviderFactory and picker see them like any provider.
 */
class Settings
{
    const PREFIX = 'llm_';

    protected static $reserved = ['google', 'deepl'];

    public static function get(string $key, $default = null)
    {
        return TranslateSetting::get(static::PREFIX . $key, $default);
    }

    public static function set($key, $value = null): void
    {
        $data = is_array($key) ? $key : [$key => $value];
        $prefixed = [];
        foreach ($data as $k => $v) {
            $prefixed[static::PREFIX . $k] = $v;
        }
        TranslateSetting::set($prefixed);
    }

    public static function instance(): self
    {
        return new static;
    }

    public function __get($key)
    {
        return static::get($key);
    }

    /** @return array<string,string> provider slug => label, for the auto-translate dropdown */
    public static function providerOptions(): array
    {
        $opts = [];
        foreach (static::providerConfigs() as $slug => $cfg) {
            $opts[$slug] = $slug;
        }
        return $opts;
    }

    /**
     * @return array<string, array> provider config keyed by provider slug
     */
    public static function providerConfigs(): array
    {
        $settings = static::instance();
        $endpoints = static::get('endpoints');
        if (!is_array($endpoints) || !$endpoints) {
            $endpoints = [Config::get('mercator.llmtranslate::default_endpoint')];
        }

        $out = [];
        foreach ($endpoints as $ep) {
            $slug = Str::slug((string) ($ep['name'] ?? ''), '_');
            if ($slug === '' || empty($ep['base_url']) || empty($ep['model'])) {
                continue;
            }
            if (in_array($slug, static::$reserved, true) || isset($out[$slug])) {
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
