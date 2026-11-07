<?php namespace Mercator\LlmTranslate\Updates;

use Db;
use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Moves values saved by 1.x (own settings record) onto Winter.Translate's
 * settings record, where they now live as `llm_*` keys.
 */
class MoveSettingsToTranslateProviders extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }
        $old = Db::table('system_settings')->where('item', 'mercator_llmtranslate_settings')->first();
        if (!$old) {
            return;
        }

        $oldData = json_decode($old->value, true) ?: [];
        $row = Db::table('system_settings')->where('item', 'winter_translate_settings')->first();
        $data = $row ? (json_decode($row->value, true) ?: []) : [];

        foreach ($oldData as $key => $value) {
            $data['llm_' . $key] = $value;
        }
        $json = json_encode($data);

        if ($row) {
            Db::table('system_settings')->where('item', 'winter_translate_settings')->update(['value' => $json]);
        }
        else {
            Db::table('system_settings')->insert(['item' => 'winter_translate_settings', 'value' => $json]);
        }
        Db::table('system_settings')->where('item', 'mercator_llmtranslate_settings')->delete();
    }

    public function down()
    {
    }
}
