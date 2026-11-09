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

            $out[$slug] = static::endpointConfig($ep, $settings);
        }

        return $out;
    }

    /**
     * Provider config for one endpoint row (shared by the live providers and the test button).
     */
    public static function endpointConfig(array $ep, $settings = null): array
    {
        $settings = $settings ?: static::instance();

        return [
            'class'        => LlmTranslateProvider::class,
            'format'       => ($ep['format'] ?? 'openai') === 'anthropic' ? 'anthropic' : 'openai',
            'url'          => static::normalizeBaseUrl((string) ($ep['base_url'] ?? '')),
            'model'        => (string) ($ep['model'] ?? ''),
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

    /**
     * Normalise an endpoint base URL so IPv4 and IPv6 literals both work.
     *
     * Accepts e.g. "192.168.1.5:1234/v1", "::1", "[::1]:11434/v1",
     * "fd00::5", "http://[fe80::1%en0]:8080/v1", "localhost:11434".
     * A missing scheme defaults to http://, and bare IPv6 addresses are
     * wrapped in brackets (required in URLs). Zone IDs are percent-encoded.
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $scheme = 'http://';
        if (preg_match('#^([a-z][a-z0-9+.-]*)://#i', $url, $m)) {
            $scheme = strtolower($m[1]) . '://';
            $url = substr($url, strlen($m[0]));
        }

        $path = '';
        if (($pos = strpos($url, '/')) !== false) {
            $path = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        $host = $url;
        $port = '';
        if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $url, $m)) {
            $host = $m[1];
            $port = $m[2] ?? '';
        } elseif (substr_count($url, ':') > 1) {
            // Bare IPv6 literal without brackets. If the whole string is not a
            // valid address, treat a trailing ":digits" as the port.
            $host = $url;
            if (!filter_var($url, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
                && preg_match('/^(.*):(\d+)$/', $url, $m)
                && filter_var(preg_replace('/%.*$/', '', $m[1]), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ) {
                $host = $m[1];
                $port = $m[2];
            }
        } elseif (preg_match('/^(.*):(\d+)$/', $url, $m)) {
            $host = $m[1];
            $port = $m[2];
        }

        $zone = '';
        if (($z = strpos($host, '%')) !== false) {
            $zone = '%25' . rawurlencode(preg_replace('/^25/', '', substr($host, $z + 1)));
            $host = substr($host, 0, $z);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '[' . $host . $zone . ']';
        }

        return $scheme . $host . ($port !== '' ? ':' . $port : '') . rtrim($path, '/');
    }

    public static function applyConfigValues(): void
    {
        foreach (static::providerConfigs() as $slug => $cfg) {
            Config::set("winter.translate::providers.$slug", $cfg);
        }
    }
}
