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
    public static function messages(string $provider, ?string $from, array $targets, bool $overwrite, int $max): array
    {
        $sourceLang = $from ?: static::defaultCode();
        $translator = ProviderFactory::create($provider);
        $done = $remaining = 0;

        foreach (static::cleanTargets($targets, $sourceLang) as $to) {
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
            if (!$todo) {
                continue;
            }

            $out = $translator->translate(array_column($todo, 1), $to, $sourceLang);
            Provenance::withoutTracking(function () use ($todo, $out, $to, $from, $provider) {
                foreach ($todo as $i => [$message]) {
                    $message->toLocale($to, $out[$i]);
                    Provenance::record('message', '', $message->id, '', $to, $from, $provider);
                }
            });
            $done += count($todo);
            $remaining += $total - count($todo);
        }

        return [$done, $remaining];
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
    public static function models(string $provider, string $class, ?string $from, array $targets, bool $overwrite, int $max, ?array $fields = null): array
    {
        if (!static::isTranslatableModel($class)) {
            throw new \ApplicationException("$class is not a translatable model.");
        }

        $default = static::defaultCode();
        $sourceLang = $from ?: $default;
        $translator = ProviderFactory::create($provider);
        $done = $remaining = 0;

        foreach (static::cleanTargets($targets, $sourceLang) as $to) {
            if ($to === $default) {
                continue; // the default language lives in the base columns
            }
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
            if (!$jobs) {
                continue;
            }

            $out = $translator->translate(array_column($jobs, 2), $to, $sourceLang);
            Provenance::withoutTracking(function () use ($jobs, $out, $to, $from, $provider) {
                $saved = [];
                foreach ($jobs as $i => [$model, $name]) {
                    $model->setAttributeTranslated($name, $out[$i], $to);
                    $saved[spl_object_id($model)] = $model;
                    Provenance::record('attribute', $model->getMorphClass(), (int) $model->getKey(), $name, $to, $from, $provider);
                }
                foreach ($saved as $model) {
                    $model->save();
                }
            });
            $done += count($jobs);
            $remaining += $total - count($jobs);
        }

        return [$done, $remaining];
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
}
