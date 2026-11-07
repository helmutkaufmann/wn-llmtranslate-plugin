<?php namespace Mercator\LlmTranslate\Classes;

use Log;

/**
 * Queued job: translate the changed fields of one saved record.
 */
class AutoTranslateJob
{
    public function fire($job, $data)
    {
        try {
            $class = $data['class'];
            if (BulkTranslator::isTranslatableModel($class) && ($model = $class::find($data['id']))) {
                BulkTranslator::modelFields($data['provider'], $model, $data['fields'], BulkTranslator::enabledCodes());
            }
        }
        catch (\Throwable $e) {
            Log::warning('LlmTranslate auto-translate failed: ' . $e->getMessage(), ['data' => $data]);
        }

        $job->delete();
    }
}
