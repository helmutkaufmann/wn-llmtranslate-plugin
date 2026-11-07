<?php namespace Mercator\LlmTranslate\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateProvenanceTable extends Migration
{
    public function up()
    {
        Schema::create('mercator_llmtranslate_provenance', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('kind', 16);                       // message | attribute
            $table->string('model_type', 191)->default('');   // attribute: model class
            $table->unsignedBigInteger('model_id');           // message id / model key
            $table->string('field', 100)->default('');        // attribute name
            $table->string('locale', 16);                     // target locale
            $table->string('source_locale', 16)->nullable();  // null = default
            $table->string('provider', 100);
            $table->string('llm_model', 150)->nullable();
            $table->string('status', 16)->default('machine'); // machine | reviewed
            $table->timestamp('translated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->string('note', 100)->nullable();
            $table->unique(['kind', 'model_type', 'model_id', 'field', 'locale'], 'mercator_llmt_prov_unique');
            $table->index(['status', 'locale']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mercator_llmtranslate_provenance');
    }
}
