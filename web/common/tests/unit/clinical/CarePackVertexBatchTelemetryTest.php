<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\CareCohort\Batch\CarePackVertexBatchTelemetry;
use common\components\Platform\Ai\Cost\AICostTracker;

class CarePackVertexBatchTelemetryTest extends \Codeception\Test\Unit
{
    protected function _before(): void
    {
        AICostTracker::iniciarEjecucionPrueba();
        AICostTracker::reset();
        \Yii::$app->params['ia_usage_tracking_habilitado'] = true;
    }

    protected function _after(): void
    {
        AICostTracker::finalizarEjecucionPrueba();
        AICostTracker::reset();
    }

    public function testRegistraTokensDesdeUsageMetadata(): void
    {
        $line = [
            'custom_id' => 'job-1',
            'response' => [
                'usageMetadata' => [
                    'promptTokenCount' => 100,
                    'candidatesTokenCount' => 50,
                    'cachedContentTokenCount' => 0,
                ],
            ],
        ];

        CarePackVertexBatchTelemetry::registrarLineaCompletada($line);
        $resumen = AICostTracker::getResumen();

        verify($resumen['tokens']['prompt_token_count'])->equals(100);
        verify($resumen['tokens']['candidates_token_count'])->equals(50);
        verify($resumen['por_contexto']['care-pack-vertex-batch']['llamadas'])->equals(1);
    }

    public function testRegistraLlamadaSinMetadata(): void
    {
        CarePackVertexBatchTelemetry::registrarLineaCompletada([
            'custom_id' => 'job-2',
            'response' => ['candidates' => []],
        ]);
        $resumen = AICostTracker::getResumen();

        verify($resumen['llamada_real'])->equals(1);
        verify($resumen['por_contexto']['care-pack-vertex-batch']['llamadas'])->equals(1);
    }
}
