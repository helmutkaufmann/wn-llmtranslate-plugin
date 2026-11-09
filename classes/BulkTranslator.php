<?php namespace Mercator\LlmTranslate\Classes;

use Mercator\LlmTranslate\Models\Provenance;
use Model;
use Winter\Translate\Models\Locale;
use Winter\Translate\Models\Message;
use Winter\Translate\Providers\ProviderFactory;

/**
 * Shared engine for translating UI messages and translatable model attributes
 * with a configured provider, recording provenance for each value written.
 */
class BulkTranslator
{
    /** Attributes never machine-translated (URLs/identifiers). */
    protected static function skipFields(): array
    {
        return (array) config('mercator.llmtranslate::skip_fields', ['slug']);
    }

    public static function defaultCode(): string
    {
        return optional(Locale::getDefault())->code ?: 'en';
    }

    /**
     * @param  string|null $from  source locale code, null = default language
     * @param  string[]    $targets
     * @return array{0:int,1:int} [translated, remaining]
     */
    public static function messages(string $provider, ?string $from, array $targets, bool $overwrite, int $max, ?string $token = null): array
    {
        $sourceLang = $from ?: static::defaultCode();
        $translator = ProviderFactory::create($provider);
        $chunk = static::chunkSize($provider);
        $done = $remaining = 0;

        $collect = function (string $to) use ($from, $overwrite, $max): array {
            $todo = [];
            $total = 0;
            foreach (Message::orderBy('id')->cursor() as $message) {
                $source = $message->forLocale($from);
                if (!is_string($source) || trim($source) === '') {
                    continue;
                }
                if (!$overwrite && $message->forLocale($to)) {
                    continue;
                }
                $total++;
                if (count($todo) < $max) {
                    $todo[] = [$message, $source];
                }
            }
            return [$todo, $total];
        };

        // Count first so the progress bar knows the total. Records are re-read
        // per language below, so one language's save never clobbers another's.
        $targets = static::cleanTargets($targets, $sourceLang);
        $grand = 0;
        foreach ($targets as $to) {
            $grand += count($collect($to)[0]);
        }
        Progress::start($token, $grand);

        foreach ($targets as $to) {
            [$todo, $total] = $collect($to);
            foreach (array_chunk($todo, $chunk) as $part) {
                $out = $translator->translate(array_column($part, 1), $to, $sourceLang);
                Provenance::withoutTracking(function () use ($part, $out, $to, $from, $provider) {
                    foreach ($part as $i => [$message]) {
                        $message->toLocale($to, $out[$i]);
                        Provenance::record('message', '', $message->id, '', $to, $from, $provider);
                    }
                });
                Progress::advance($token, count($part), $to);
            }
            $done += count($todo);
            $remaining += $total - count($todo);
        }
        Progress::finish($token);

        return [$done, $remaining];
    }

    /** Items per provider request; one progress step each. */
    protected static function chunkSize(string $provider): int
    {
        return max(1, (int) config("winter.translate::providers.$provider.batch_size", 20));
    }

    /**
     * Model classes that can be translated: configured in settings, and
     * verified to use the Winter.Translate TranslatableModel behavior.
     */
    public static function isTranslatableModel(string $class): bool
    {
        return class_exists($class)
            && is_subclass_of($class, Model::class)
            && (new $class)->methodExists('getTranslatableAttributes');
    }

    /**
     * @param  string[]|null $fields  limit to these attributes (default: all string ones)
     * @return array{0:int,1:int} [translated values, remaining]
     */
    public static function models(string $provider, string $class, ?string $from, array $targets, bool $overwrite, int $max, ?array $fields = null, ?string $token = null): array
    {
        if (!static::isTranslatableModel($class)) {
            throw new \ApplicationException("$class is not a translatable model.");
        }

        $default = static::defaultCode();
        $sourceLang = $from ?: $default;
        $translator = ProviderFactory::create($provider);
        $chunk = static::chunkSize($provider);
        $done = $remaining = 0;

        $collect = function (string $to) use ($class, $fields, $sourceLang, $overwrite, $max): array {
            $jobs = [];
            $total = 0;
            foreach ($class::query()->orderBy((new $class)->getKeyName())->cursor() as $model) {
                $model->noFallbackLocale();
                $names = $fields ?: $model->getTranslatableAttributes();
                foreach ($names as $name) {
                    if (in_array($name, static::skipFields(), true)) {
                        continue;
                    }
                    $source = $model->getAttributeTranslated($name, $sourceLang);
                    if (!is_string($source) || trim($source) === '') {
                        continue;
                    }
                    if (!$overwrite && $model->getAttributeTranslated($name, $to)) {
                        continue;
                    }
                    $total++;
                    if (count($jobs) < $max) {
                        $jobs[] = [$model, $name, $source];
                    }
                }
            }
            return [$jobs, $total];
        };

        // The default language lives in the base columns: never a target.
        $targets = array_values(array_filter(static::cleanTargets($targets, $sourceLang), function ($to) use ($default) {
            return $to !== $default;
        }));
        $grand = 0;
        foreach ($targets as $to) {
            $grand += count($collect($to)[0]);
        }
        Progress::start($token, $grand);

        foreach ($targets as $to) {
            [$jobs, $total] = $collect($to);
            foreach (array_chunk($jobs, $chunk) as $part) {
                $out = $translator->translate(array_column($part, 2), $to, $sourceLang);
                Provenance::withoutTracking(function () use ($part, $out, $to, $from, $provider) {
                    $saved = [];
                    foreach ($part as $i => [$model, $name]) {
                        $model->setAttributeTranslated($name, $out[$i], $to);
                        $saved[spl_object_id($model)] = $model;
                        Provenance::record('attribute', $model->getMorphClass(), (int) $model->getKey(), $name, $to, $from, $provider);
                    }
                    foreach ($saved as $model) {
                        $model->save();
                    }
                });
                Progress::advance($token, count($part), $to);
            }
            $done += count($jobs);
            $remaining += $total - count($jobs);
        }
        Progress::finish($token);

        return [$done, $remaining];
    }

    /**
     * Translates the given default-language fields of ONE record into the
     * targets. Human or reviewed translations are never touched: only empty
     * values and still-unreviewed machine translations (now stale) are written.
     *
     * @return int number of values written
     */
    public static function modelFields(string $provider, Model $model, array $fields, array $targets): int
    {
        $default = static::defaultCode();
        $translator = ProviderFactory::create($provider);
        $model->noFallbackLocale();
        $written = 0;

        foreach (static::cleanTargets($targets, $default) as $to) {
            $jobs = [];
            foreach ($fields as $name) {
                if (in_array($name, static::skipFields(), true)) {
                    continue;
                }
                $source = $model->getAttributeTranslated($name, $default);
                if (!is_string($source) || trim($source) === '') {
                    continue;
                }
                if ($model->getAttributeTranslated($name, $to)) {
                    $isMachine = Provenance::where([
                        'kind' => 'attribute', 'model_type' => $model->getMorphClass(), 'model_id' => $model->getKey(),
                        'field' => $name, 'locale' => $to, 'status' => Provenance::MACHINE,
                    ])->exists();
                    if (!$isMachine) {
                        continue;
                    }
                }
                $jobs[] = [$name, $source];
            }
            if (!$jobs) {
                continue;
            }

            $out = $translator->translate(array_column($jobs, 1), $to, $default);
            Provenance::withoutTracking(function () use ($jobs, $out, $to, $provider, $model) {
                foreach ($jobs as $i => [$name]) {
                    $model->setAttributeTranslated($name, $out[$i], $to);
                    Provenance::record('attribute', $model->getMorphClass(), (int) $model->getKey(), $name, $to, null, $provider);
                }
                $model->save();
            });
            $written += count($jobs);
        }

        return $written;
    }

    /** @return string[] */
    protected static function cleanTargets(array $targets, string $sourceLang): array
    {
        return array_values(array_filter(array_unique($targets), function ($code) use ($sourceLang) {
            return $code && $code !== $sourceLang && Locale::findByCode($code);
        }));
    }

    public static function enabledCodes(): array
    {
        return Locale::isEnabled()->pluck('code')->all();
    }

    /** Model classes listed in the settings, filtered to valid translatable ones. */
    public static function configuredModels(): array
    {
        $lines = preg_split('/\R/', (string) \Mercator\LlmTranslate\Models\Settings::get('models', ''));
        $out = [];
        foreach ($lines as $line) {
            $class = ltrim(trim($line), '\\');
            if ($class !== '' && static::isTranslatableModel($class)) {
                $out[] = $class;
            }
        }
        return array_values(array_unique($out));
    }

    /** Models with auto-translate on save enabled (subset of the configured ones). */
    public static function autoModels(): array
    {
        if (!\Mercator\LlmTranslate\Models\Settings::get('auto_enabled')) {
            return [];
        }
        $lines = preg_split('/\R/', (string) \Mercator\LlmTranslate\Models\Settings::get('auto_models', ''));
        $configured = static::configuredModels();
        $out = [];
        foreach ($lines as $line) {
            $class = ltrim(trim($line), '\\');
            if ($class !== '' && in_array($class, $configured, true)) {
                $out[] = $class;
            }
        }
        return array_values(array_unique($out));
    }
}
