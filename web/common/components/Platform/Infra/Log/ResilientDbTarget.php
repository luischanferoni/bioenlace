<?php

namespace common\components\Platform\Infra\Log;

use Yii;
use yii\helpers\FileHelper;
use yii\log\DbTarget;
use yii\log\FileTarget;
use yii\log\Logger;

/**
 * DbTarget que no amplifica "MySQL server has gone away": si falla el INSERT en log, escribe en archivo.
 */
class ResilientDbTarget extends DbTarget
{
    public function export(): void
    {
        try {
            parent::export();
        } catch (\Throwable $e) {
            Yii::warning(
                'ResilientDbTarget: no se pudo exportar a BD (' . $e->getMessage() . ')',
                __METHOD__
            );
            $this->exportToFallbackFile();
        }
    }

    private function exportToFallbackFile(): void
    {
        $dir = Yii::getAlias('@runtime/logs');
        FileHelper::createDirectory($dir);
        $fallback = new FileTarget([
            'logFile' => $dir . '/db-target-fallback.log',
            'exportInterval' => 1,
            'levels' => [Logger::LEVEL_ERROR, Logger::LEVEL_WARNING, Logger::LEVEL_INFO],
        ]);
        $fallback->messages = $this->messages;
        $fallback->export();
    }
}
