<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Workflow\EncounterCaptureAnalysisCache;
use Yii;

class EncounterCaptureAnalysisCacheTest extends Unit
{
    public function testStoreAndRecallByTokenAndContext(): void
    {
        $body = [
            'id_persona' => 920778,
            'parent' => 'TURNO',
            'parent_id' => 3461311,
            'encounter_id' => 21,
        ];
        $extraidos = [
            'Motivos de consulta' => ['cefalea tensional'],
            'Diagnóstico' => ['hipertensión arterial esencial en seguimiento inicial'],
            'Medicación' => [
                [
                    'Nombre del medicamento' => 'enalapril',
                    'Cantidad' => '10 mg',
                ],
            ],
            'Indicaciones' => [
                ['Indicacion' => 'Reposo relativo'],
                ['Indicacion' => 'Control', 'Plazo dias' => 15],
            ],
        ];
        $texto = 'Cefalea tensional de una semana. Diagnóstico: hipertensión. Indico enalapril 10 mg';

        $token = EncounterCaptureAnalysisCache::store($body, $extraidos, $texto);
        $this->assertNotNull($token);

        $meta = EncounterCaptureAnalysisCache::recallWithMeta([
            'analysis_cache_token' => $token,
        ]);
        $this->assertNotSame('none', $meta['fuente']);
        $this->assertArrayHasKey('Medicación', $meta['extraidos']);
        $this->assertSame('enalapril', $meta['extraidos']['Medicación'][0]['Nombre del medicamento']);

        $byContext = EncounterCaptureAnalysisCache::recall($body, $texto);
        $this->assertArrayHasKey('Indicaciones', $byContext);
    }

    protected function _after(): void
    {
        parent::_after();
        try {
            Yii::$app->cache->flush();
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
