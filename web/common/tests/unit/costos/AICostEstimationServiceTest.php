<?php

namespace common\tests\unit\costos;

use common\components\Platform\Ai\Cost\AICostEstimationService;
use common\components\Platform\Ai\Cost\AICostReferenceMetadata;

class AICostEstimationServiceTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        AICostReferenceMetadata::resetCacheForTests();
    }

    public function testEstimaDesdeTokensMedidos()
    {
        $resumen = [
            'llamada_simulada' => 0,
            'por_contexto' => [],
            'tokens' => [
                'prompt_token_count' => 1000,
                'cached_content_token_count' => 250,
                'candidates_token_count' => 500,
            ],
        ];

        $est = AICostEstimationService::estimarDesdeResumen($resumen);
        verify($est['fuente_tokens'])->equals('medido');
        verify($est['billable_input_tokens'])->equals(750);
        verify($est['usd']['total'])->greaterThan(0);
    }

    public function testProyectaDesdeLlamadasSimuladas()
    {
        $resumen = [
            'llamada_simulada' => 2,
            'llamada_real' => 0,
            'por_contexto' => [
                'asistente-preprocess' => ['llamadas' => 2],
            ],
            'tokens' => [
                'prompt_token_count' => 0,
                'cached_content_token_count' => 0,
                'candidates_token_count' => 0,
            ],
        ];

        $est = AICostEstimationService::estimarDesdeResumen($resumen);
        verify($est['fuente_tokens'])->equals('referencia');
        verify($est['prompt_tokens'])->equals(1400);
        verify($est['usd']['total'])->greaterThan(0);
    }
}
