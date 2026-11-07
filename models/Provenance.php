<?php namespace Mercator\LlmTranslate\Models;

use BackendAuth;
use Model;
use Winter\Translate\Models\Locale;
use Winter\Translate\Models\Message;

/**
 * One row per machine-translated value (message x locale, or model attribute x
 * locale). A human edit flips the row to "reviewed" (see Plugin hooks), and a
 * reviewer can also approve it explicitly.
 */
class Provenance extends Model
{
    const MACHINE = 'machine';
    const REVIEWED = 'reviewed';

    public $table = 'mercator_llmtranslate_provenance';
    public $timestamps = false;
    protected $guarded = [];
    protected $dates = ['translated_at', 'reviewed_at'];

    /** True while the plugin itself writes translations (edits are not human). */
    public static bool $writing = false;

    public static function withoutTracking(callable $fn)
    {
        $prev = static::$writing;
        static::$writing = true;
        try {
            return $fn();
        }
        finally {
            static::$writing = $prev;
        }
    }

    public static function record(string $kind, string $type, int $id, string $field, string $locale, ?string $sourceLocale, string $provider): void
    {
        static::updateOrCreate(
            ['kind' => $kind, 'model_type' => $type, 'model_id' => $id, 'field' => $field, 'locale' => $locale],
            [
                'source_locale' => $sourceLocale,
                'provider'      => $provider,
                'llm_model'     => config("winter.translate::providers.$provider.model"),
                'status'        => static::MACHINE,
                'translated_at' => now(),
                'reviewed_at'   => null,
                'reviewed_by'   => null,
                'note'          => null,
            ]
        );
    }

    /** A person changed the stored value: it no longer needs machine review. */
    public static function markEdited(string $kind, string $type, int $id, string $field, string $locale): void
    {
        if (static::$writing) {
            return;
        }
        static::where(compact('kind') + ['model_type' => $type, 'model_id' => $id, 'field' => $field, 'locale' => $locale])
            ->where('status', static::MACHINE)
            ->update(['status' => static::REVIEWED, 'reviewed_at' => now(), 'note' => 'edited',
                      'reviewed_by' => optional(BackendAuth::getUser())->id]);
    }

    public function approve(): void
    {
        $this->update(['status' => static::REVIEWED, 'reviewed_at' => now(), 'note' => null,
                       'reviewed_by' => optional(BackendAuth::getUser())->id]);
    }

    public function isMachine(): bool
    {
        return $this->status === static::MACHINE;
    }

    protected function targetModel()
    {
        if ($this->kind === 'message') {
            return Message::find($this->model_id);
        }
        $class = $this->model_type;
        return class_exists($class) ? $class::find($this->model_id) : null;
    }

    public function label(): string
    {
        if ($this->kind === 'message') {
            $code = (string) optional($this->targetModel())->code;
            // message codes are usually md5 hashes: not meaningful to a reviewer
            return ($code === '' || preg_match('/^[a-f0-9]{32}$/', $code)) ? '#' . $this->model_id : $code;
        }
        return class_basename($this->model_type) . ' #' . $this->model_id . ' / ' . $this->field;
    }

    public function sourceText(): ?string
    {
        $m = $this->targetModel();
        if (!$m) {
            return null;
        }
        if ($this->kind === 'message') {
            return $m->forLocale($this->source_locale ?: null);
        }
        $from = $this->source_locale ?: optional(Locale::getDefault())->code;
        return (string) $m->noFallbackLocale()->getAttributeTranslated($this->field, $from);
    }

    public function targetText(): ?string
    {
        $m = $this->targetModel();
        if (!$m) {
            return null;
        }
        if ($this->kind === 'message') {
            return $m->forLocale($this->locale);
        }
        return (string) $m->noFallbackLocale()->getAttributeTranslated($this->field, $this->locale);
    }

    /**
     * Removes the translated value (if the item still exists) and this record.
     * Only the target locale is touched; the source is never modified.
     */
    public function discard(): void
    {
        if ($m = $this->targetModel()) {
            static::withoutTracking(function () use ($m) {
                if ($this->kind === 'message') {
                    $m->toLocale($this->locale, null); // null removes the locale entry
                }
                else {
                    $m->setAttributeTranslated($this->field, '', $this->locale);
                    $m->save();
                }
            });
        }
        $this->delete();
    }

    /** Drops records of items that were deleted. */
    public static function forget(string $kind, string $type, int $id): void
    {
        static::where(['kind' => $kind, 'model_type' => $type, 'model_id' => $id])->delete();
    }

    /** Saves a reviewer's corrected text and marks the row reviewed. */
    public function saveTargetText(string $text): void
    {
        $m = $this->targetModel();
        if (!$m) {
            throw new \ApplicationException('The translated item no longer exists.');
        }
        if (trim($text) === '') {
            $this->discard();
            return;
        }
        static::withoutTracking(function () use ($m, $text) {
            if ($this->kind === 'message') {
                $m->toLocale($this->locale, $text);
            }
            else {
                $m->setAttributeTranslated($this->field, $text, $this->locale);
                $m->save();
            }
        });
        $this->approve();
        $this->update(['note' => 'edited']);
    }
}
