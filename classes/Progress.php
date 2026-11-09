<?php namespace Mercator\LlmTranslate\Classes;

use Cache;

/**
 * Progress of a running bulk translation, shared between the long-running
 * request and the short polling requests of the progress bar (via the cache).
 */
class Progress
{
    protected static function key(?string $token): string
    {
        return 'mercator.llmtranslate.progress.' . preg_replace('/\W/', '', (string) $token);
    }

    public static function start(?string $token, int $total): void
    {
        if (!$token) {
            return;
        }
        Cache::put(static::key($token), [
            'total' => $total, 'done' => 0, 'label' => '', 'finished' => false, 'started' => time(),
        ], 3600);
    }

    public static function advance(?string $token, int $by, string $label = ''): void
    {
        if (!$token || !($p = Cache::get(static::key($token)))) {
            return;
        }
        $p['done'] = min($p['total'], $p['done'] + $by);
        $p['label'] = $label;
        Cache::put(static::key($token), $p, 3600);
    }

    public static function finish(?string $token): void
    {
        if (!$token || !($p = Cache::get(static::key($token)))) {
            return;
        }
        $p['finished'] = true;
        $p['done'] = $p['total'];
        Cache::put(static::key($token), $p, 600);
    }

    public static function get(?string $token): array
    {
        return (array) Cache::get(static::key($token), ['total' => 0, 'done' => 0, 'label' => '', 'finished' => false, 'started' => 0]);
    }
}
