<?php namespace Mercator\LlmTranslate;

use App;
use Backend\Facades\Backend;
use Event;
use Flash;
use Lang;
use System\Classes\PluginBase;
use Winter\Translate\Controllers\Messages;
use Winter\Translate\Models\Locale;
use Winter\Translate\Models\Message;
use Mercator\LlmTranslate\Classes\BulkTranslator;
use Mercator\LlmTranslate\Models\Provenance;
use Mercator\LlmTranslate\Models\Settings;
use Winter\Translate\Models\Setting as TranslateSetting;

/**
 * Mercator.LlmTranslate - adds OpenAI-/Anthropic-compatible translation
 * providers to Winter.Translate without modifying it.
 */
class Plugin extends PluginBase
{
    public $require = ['Winter.Translate'];

    public function pluginDetails()
    {
        return [
            'name'        => 'mercator.llmtranslate::lang.plugin.name',
            'description' => 'mercator.llmtranslate::lang.plugin.description',
            'author'      => 'Mercator',
            'icon'        => 'icon-language',
        ];
    }

    public function registerPermissions()
    {
        return [
            'mercator.llmtranslate.manage_settings' => [
                'label' => 'mercator.llmtranslate::lang.permissions.manage_settings',
                'tab'   => 'mercator.llmtranslate::lang.plugin.name',
            ],
            'mercator.llmtranslate.review' => [
                'label' => 'mercator.llmtranslate::lang.permissions.review',
                'tab'   => 'mercator.llmtranslate::lang.plugin.name',
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'review' => [
                'label'       => 'mercator.llmtranslate::lang.review.label',
                'description' => 'mercator.llmtranslate::lang.review.description',
                'icon'        => 'icon-check-square-o',
                'url'         => Backend::url('mercator/llmtranslate/review'),
                'order'       => 554,
                'category'    => 'winter.translate::lang.plugin.name',
                'permissions' => ['mercator.llmtranslate.review'],
            ],
        ];
    }

    public function boot()
    {
        // Needed in the backend (picker, Messages screen) and in console
        // processes (artisan command, queue worker running auto-translate jobs).
        if (App::runningInBackend() || App::runningInConsole()) {
            try {
                Settings::applyConfigValues();
            }
            catch (\Throwable $e) {
                // settings table missing during install/migrate
            }
        }

        $this->extendMessagesController();
        $this->extendProviderSettingsForm();
        $this->trackHumanEdits();
    }

    public function register()
    {
        // Must be registered before any plugin boots (Winter.Translate caches its
        // settings instance during its own boot).
        TranslateSetting::extend(function ($model) {
            $model->addDynamicMethod('getLlmAutoProviderOptions', function () {
                return Settings::providerOptions();
            });
        });

        $this->registerConsoleCommand('llmtranslate.models', Console\TranslateModels::class);
    }

    /**
     * A person changing a machine-translated value turns it into "reviewed".
     * The plugin's own writes are excluded via Provenance::withoutTracking().
     */
    protected function trackHumanEdits(): void
    {
        Message::extend(function ($message) {
            $message->bindEvent('model.afterSave', function () use ($message) {
                $old = $message->getOriginal('message_data');
                $old = is_string($old) ? (json_decode($old, true) ?: []) : (array) $old;
                $new = (array) $message->message_data;
                foreach (array_keys(array_diff_assoc($new, $old)) as $locale) {
                    Provenance::markEdited('message', '', (int) $message->id, '', (string) $locale);
                }
            });
        });

        // Attribute translations are written with raw queries by Winter.Translate
        // (no Attribute model events), so hook the translatable models themselves.
        // Runs before the behavior syncs (higher priority) so dirty state is intact.
        if (!App::runningInBackend() && !App::runningInConsole()) {
            return;
        }
        try {
            $classes = BulkTranslator::configuredModels();
        }
        catch (\Throwable $e) {
            return;
        }
        $this->registerAutoTranslate();

        foreach ($classes as $class) {
            $class::extend(function ($model) {
                $model->bindEvent('model.saveInternal', function () use ($model) {
                    if (!$model->getKey()) {
                        return;
                    }
                    foreach ($model->getDirtyLocales() as $locale) {
                        foreach (array_keys($model->getTranslateDirty($locale)) as $field) {
                            Provenance::markEdited('attribute', $model->getMorphClass(), (int) $model->getKey(), (string) $field, (string) $locale);
                        }
                    }
                }, 100);
            });
        }
    }

    /**
     * Opt-in: when a listed model is saved with changed default-language text,
     * queue a job translating just those fields into the other languages.
     */
    protected function registerAutoTranslate(): void
    {
        try {
            $classes = BulkTranslator::autoModels();
            $provider = (string) Settings::get('auto_provider');
        }
        catch (\Throwable $e) {
            return;
        }
        if (!$classes || !array_key_exists($provider, static::usableProviders())) {
            return;
        }

        foreach ($classes as $class) {
            $class::extend(function ($model) use ($provider) {
                $model->bindEvent('model.afterSave', function () use ($model, $provider) {
                    if (Provenance::$writing) {
                        return;
                    }
                    $fields = array_values(array_intersect(
                        array_keys($model->getDirty()),
                        $model->getTranslatableAttributes()
                    ));
                    if (!$fields) {
                        return;
                    }
                    \Queue::push(Classes\AutoTranslateJob::class, [
                        'class'    => get_class($model),
                        'id'       => $model->getKey(),
                        'fields'   => $fields,
                        'provider' => $provider,
                    ]);
                });
            });
        }
    }

    /**
     * Adds an "LLM" tab to Settings -> Translation Providers.
     */
    protected function extendProviderSettingsForm(): void
    {
        Event::listen('backend.form.extendFields', function ($widget) {
            if (!$widget->model instanceof TranslateSetting || $widget->isNested) {
                return;
            }

            $tab = 'mercator.llmtranslate::lang.settings.tab';
            $fields = [];
            $defs = \Yaml::parseFile(plugins_path('mercator/llmtranslate/models/settings/fields.yaml'))['fields'];
            foreach ($defs as $name => $def) {
                $def['tab'] = $tab;
                if (isset($def['trigger']['field'])) {
                    $def['trigger']['field'] = Settings::PREFIX . $def['trigger']['field'];
                }
                $fields[Settings::PREFIX . $name] = $def;
            }
            $widget->addTabFields($fields);
        });
    }

    protected function extendMessagesController(): void
    {
        Messages::extend(function (Messages $controller) {
            $controller->addJs('/plugins/mercator/llmtranslate/assets/js/messages-button.js');

            $controller->addDynamicMethod('onLoadMachineTranslateForm', function () use ($controller) {
                return $controller->makePartial('$/mercator/llmtranslate/partials/_machine_translate_form', [
                    'providers'   => array_keys(Plugin::usableProviders()),
                    'localeFrom'  => (string) post('locale_from'),
                    'localeTo'    => (string) post('locale_to'),
                    'defaultCode' => optional(Locale::getDefault())->code,
                    'max'         => (int) config('mercator.llmtranslate::max_per_run', 200),
                ]);
            });

            $controller->addDynamicMethod('onMachineTranslateMessages', function () use ($controller) {
                return Plugin::runMachineTranslate($controller);
            });
        });
    }

    /**
     * Providers configured and usable (have a key, or are keyless local LLMs).
     */
    public static function usableProviders(): array
    {
        return array_filter((array) config('winter.translate::providers', []), function ($c) {
            return is_array($c) && !empty($c['key']);
        });
    }

    public static function runMachineTranslate(Messages $controller)
    {
        $provider = (string) post('provider');
        if (!array_key_exists($provider, static::usableProviders())) {
            throw new \ApplicationException(Lang::get('mercator.llmtranslate::lang.messages.no_provider'));
        }

        $targets = post('all_languages') ? BulkTranslator::enabledCodes() : array_filter([(string) post('locale_to')]);
        if (!$targets) {
            throw new \ApplicationException(Lang::get('mercator.llmtranslate::lang.messages.no_target'));
        }

        @set_time_limit(0);
        [$done, $remaining] = BulkTranslator::messages(
            $provider,
            (string) post('locale_from') ?: null,
            $targets,
            (bool) post('overwrite'),
            (int) config('mercator.llmtranslate::max_per_run', 200)
        );

        static::flashResult($done, $remaining);

        return $controller->onRefresh();
    }

    public static function flashResult(int $done, int $remaining): void
    {
        if (!$done) {
            Flash::info(Lang::get('mercator.llmtranslate::lang.messages.nothing'));
        }
        else {
            Flash::success(Lang::get(
                $remaining > 0 ? 'mercator.llmtranslate::lang.messages.done_remaining' : 'mercator.llmtranslate::lang.messages.done',
                ['count' => $done, 'remaining' => $remaining]
            ));
        }
    }
}
