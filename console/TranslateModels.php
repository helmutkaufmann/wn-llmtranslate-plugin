<?php namespace Mercator\LlmTranslate\Console;

use Illuminate\Console\Command;
use Mercator\LlmTranslate\Classes\BulkTranslator;

class TranslateModels extends Command
{
    protected $signature = 'llmtranslate:models
        {model : Fully qualified model class (must use the TranslatableModel behavior)}
        {--provider= : Translation provider name (default: first configured LLM endpoint)}
        {--from= : Source locale (default: the default locale)}
        {--to=* : Target locale(s); omit for all enabled locales}
        {--overwrite : Replace existing translations}
        {--limit=200 : Max values translated per target locale}';

    protected $description = 'Machine-translate the translatable attributes of a model with an LLM provider.';

    public function handle()
    {
        $providers = array_keys(array_filter((array) config('winter.translate::providers'), fn ($c) => !empty($c['key'])));
        $provider = $this->option('provider') ?: ($providers[2] ?? $providers[0] ?? null);
        if (!$provider || !in_array($provider, $providers, true)) {
            $this->error('No usable provider. Available: ' . implode(', ', $providers));
            return 1;
        }

        $targets = $this->option('to') ?: BulkTranslator::enabledCodes();
        [$done, $remaining] = BulkTranslator::models(
            $provider,
            ltrim($this->argument('model'), '\\'),
            $this->option('from') ?: null,
            $targets,
            (bool) $this->option('overwrite'),
            (int) $this->option('limit')
        );

        $this->info("$done values translated via '$provider', $remaining remaining.");
        return 0;
    }
}
