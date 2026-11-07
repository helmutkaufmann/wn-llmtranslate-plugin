<?php namespace Mercator\LlmTranslate;

use App;
use Backend\Facades\Backend;
use Flash;
use Lang;
use System\Classes\PluginBase;
use Winter\Translate\Controllers\Messages;
use Winter\Translate\Models\Locale;
use Winter\Translate\Models\Message;
use Winter\Translate\Providers\ProviderFactory;
use Mercator\LlmTranslate\Models\Settings;

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
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'mercator.llmtranslate::lang.settings.label',
                'description' => 'mercator.llmtranslate::lang.settings.description',
                'icon'        => 'icon-language',
                'class'       => Settings::class,
                'order'       => 553,
                'category'    => 'winter.translate::lang.plugin.name',
                'permissions' => ['mercator.llmtranslate.manage_settings'],
                'keywords'    => 'llm openai anthropic ollama translation machine translate',
            ],
        ];
    }

    public function boot()
    {
        // Providers are only needed in the backend (copy picker, Messages screen).
        if (App::runningInBackend()) {
            try {
                Settings::applyConfigValues();
            }
            catch (\Throwable $e) {
                // settings table missing during install/migrate
            }
        }

        $this->extendMessagesController();
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

        $fromCode = (string) post('locale_from') ?: null;
        $sourceLang = $fromCode ?: optional(Locale::getDefault())->code ?: 'en';

        if (post('all_languages')) {
            $targets = Locale::isEnabled()->pluck('code')->all();
        }
        else {
            $targets = array_filter([(string) post('locale_to')]);
        }
        $targets = array_values(array_filter($targets, function ($code) use ($sourceLang) {
            return $code !== $sourceLang && Locale::findByCode($code);
        }));
        if (!$targets) {
            throw new \ApplicationException(Lang::get('mercator.llmtranslate::lang.messages.no_target'));
        }

        $overwrite = (bool) post('overwrite');
        $max = (int) config('mercator.llmtranslate::max_per_run', 200);
        $translator = ProviderFactory::create($provider);
        @set_time_limit(0);

        $done = 0;
        $remaining = 0;
        foreach ($targets as $toCode) {
            $todo = [];
            $total = 0;
            foreach (Message::orderBy('id')->cursor() as $message) {
                $source = $message->forLocale($fromCode);
                if (!is_string($source) || trim($source) === '') {
                    continue;
                }
                if (!$overwrite && $message->forLocale($toCode)) {
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

            $translated = $translator->translate(array_column($todo, 1), $toCode, $sourceLang);
            foreach ($todo as $i => [$message]) {
                $message->toLocale($toCode, $translated[$i]);
            }
            $done += count($todo);
            $remaining += $total - count($todo);
        }

        if (!$done) {
            Flash::info(Lang::get('mercator.llmtranslate::lang.messages.nothing'));
        }
        else {
            Flash::success(Lang::get(
                $remaining > 0 ? 'mercator.llmtranslate::lang.messages.done_remaining' : 'mercator.llmtranslate::lang.messages.done',
                ['count' => $done, 'remaining' => $remaining]
            ));
        }

        return $controller->onRefresh();
    }
}
