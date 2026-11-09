<?php namespace Mercator\LlmTranslate\Controllers;

use ApplicationException;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Lang;
use Mercator\LlmTranslate\Classes\BulkTranslator;
use Mercator\LlmTranslate\Models\Provenance;
use Mercator\LlmTranslate\Plugin;
use System\Classes\SettingsManager;
use Winter\Translate\Models\Locale;

/**
 * Review screen for machine translations: filter, correct, approve.
 */
class Review extends Controller
{
    public $requiredPermissions = ['mercator.llmtranslate.review'];

    const PER_PAGE = 25;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Winter.System', 'system', 'settings');
        SettingsManager::setContext('Mercator.LlmTranslate', 'review');
        $this->addCss('/plugins/mercator/llmtranslate/assets/css/review.css');
        $this->addJs('/plugins/mercator/llmtranslate/assets/js/review.js');
        $this->addJs('/plugins/mercator/llmtranslate/assets/js/mt-progress.js');
        $this->addCss('/plugins/mercator/llmtranslate/assets/css/mt-progress.css');
    }

    public function index()
    {
        $this->pageTitle = 'mercator.llmtranslate::lang.review.label';
        $this->prepare();
    }

    protected function filtered()
    {
        $q = Provenance::query();
        if (($status = post('status', 'machine')) !== 'all') {
            $q->where('status', $status);
        }
        foreach (['kind', 'locale', 'provider'] as $col) {
            if ($v = post($col)) {
                $q->where($col, $v);
            }
        }
        return $q;
    }

    protected function prepare()
    {
        $page = max(1, (int) post('page', 1));
        $total = $this->filtered()->count();

        $this->vars['rows'] = $this->filtered()->orderBy('id')->skip(($page - 1) * static::PER_PAGE)->take(static::PER_PAGE)->get();
        $this->vars['total'] = $total;
        $this->vars['page'] = $page;
        $this->vars['pages'] = max(1, (int) ceil($total / static::PER_PAGE));
        $this->vars['f'] = [
            'status'   => post('status', 'machine'),
            'kind'     => post('kind', ''),
            'locale'   => post('locale', ''),
            'provider' => post('provider', ''),
        ];
        $this->vars['locales'] = Provenance::distinct()->pluck('locale')->all();
        $this->vars['providers'] = Provenance::distinct()->pluck('provider')->all();
        $this->vars['unreviewed'] = Provenance::where('status', Provenance::MACHINE)->count();
    }

    protected function refresh()
    {
        $this->prepare();
        return ['#reviewContainer' => $this->makePartial('list')];
    }

    public function onFilter()
    {
        return $this->refresh();
    }

    public function onApprove()
    {
        Provenance::findOrFail((int) post('id'))->approve();
        return $this->refresh();
    }

    /** Approves every unreviewed row matching the current filter. */
    public function onApproveAll()
    {
        $n = $this->filtered()->where('status', Provenance::MACHINE)->update([
            'status' => Provenance::REVIEWED, 'reviewed_at' => now(),
            'reviewed_by' => optional($this->user)->id,
        ]);
        Flash::success(Lang::get('mercator.llmtranslate::lang.review.approved_n', ['count' => $n]));
        $_POST['page'] = 1;
        return $this->refresh();
    }

    public function onSaveRow()
    {
        $id = (int) post('id');
        Provenance::findOrFail($id)->saveTargetText((string) post("text_$id"));
        Flash::success(Lang::get('mercator.llmtranslate::lang.review.saved'));
        return $this->refresh();
    }

    /** Deletes one translation (value + record). Also removes records of vanished items. */
    public function onDelete()
    {
        Provenance::findOrFail((int) post('id'))->discard();
        Flash::success(Lang::get('mercator.llmtranslate::lang.review.deleted_n', ['count' => 1]));
        return $this->refresh();
    }

    /** Deletes every translation matching the current filter (all pages, any status). */
    public function onDeleteAll()
    {
        $n = 0;
        $this->filtered()->orderBy('id')->chunkById(100, function ($rows) use (&$n) {
            foreach ($rows as $row) {
                $row->discard();
                $n++;
            }
        });
        Flash::success(Lang::get('mercator.llmtranslate::lang.review.deleted_n', ['count' => $n]));
        request()->request->set('page', 1);
        return $this->refresh();
    }

    /* ---- Model attribute translation ---- */

    public function onLoadTranslateModels()
    {
        return $this->makePartial('translate_models_form', [
            'providers' => array_keys(Plugin::usableProviders()),
            'models'    => BulkTranslator::configuredModels(),
            'locales'   => Locale::isEnabled()->get(),
            'default'   => BulkTranslator::defaultCode(),
            'max'       => (int) config('mercator.llmtranslate::max_per_run', 200),
            'token'     => \Str::random(24),
        ]);
    }

    public function onMtProgress()
    {
        return \Mercator\LlmTranslate\Classes\Progress::get((string) post('progress_token'));
    }

    public function onTranslateModels()
    {
        $provider = (string) post('provider');
        if (!array_key_exists($provider, Plugin::usableProviders())) {
            throw new ApplicationException(Lang::get('mercator.llmtranslate::lang.messages.no_provider'));
        }
        $class = (string) post('model_class');
        if (!in_array($class, BulkTranslator::configuredModels(), true)) {
            throw new ApplicationException(Lang::get('mercator.llmtranslate::lang.models.no_model'));
        }
        $targets = post('all_languages') ? BulkTranslator::enabledCodes() : array_filter([(string) post('locale_to')]);
        if (!$targets) {
            throw new ApplicationException(Lang::get('mercator.llmtranslate::lang.messages.no_target'));
        }

        @set_time_limit(0);
        [$done, $remaining] = BulkTranslator::models(
            $provider, $class, (string) post('locale_from') ?: null, $targets,
            (bool) post('overwrite'), (int) config('mercator.llmtranslate::max_per_run', 200),
            null, (string) post('progress_token') ?: null
        );
        Plugin::flashResult($done, $remaining);

        return $this->refresh();
    }
}
