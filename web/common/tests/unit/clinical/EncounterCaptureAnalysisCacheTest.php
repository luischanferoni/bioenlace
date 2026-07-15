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
            'parent_id' => 3461310,
            'encounter_id' => 20,
        ];
        $extraidos = [
            'Motivos de consulta' => ['cefalea tensional'],
            'Diagnóstico' => ['hipertensión arterial esencial en seguimiento inicial'],
            'Medicación' => [
                [
                    'Nombre del medicamento' => 'enalapril',
                    'Cantidad' => '10 mg',
                    'Via de administracion' => 'oral',
                    'Frecuencia de administracion' => 'una vez al día',
                    'Tipo de frecuencia' => 'DIA',
                    'Duracion del tratamiento' => '30 días',
                    'Tipo de duracion' => 'DIA',
                ],
            ],
            'Indicaciones' => [
                ['Indicacion' => 'Reposo relativo'],
                ['Indicacion' => 'baja de sal'],
                ['Indicacion' => 'Control', 'Plazo dias' => 15],
            ],
        ];
        $texto = 'Cefalea tensional de una semana. Diagnóstico: hipertensión. Indico enalapril 10 mg';

        $token = EncounterCaptureAnalysisCache::store($body, $extraidos, $texto);
        $this->assertNotNull($token);

        $byToken = EncounterCaptureAnalysisCache::recall([
            'analysis_cache_token' => $token,
        ]);
        $this->assertArrayHasKey('Medicación', $byToken);
        $this->assertSame('enalapril', $byToken['Medicación'][0]['Nombre del medicamento']);

        $byContext = EncounterCaptureAnalysisCache::recall($body, $texto);
        $this->assertArrayHasKey('Indicaciones', $byContext);
        $this->assertCount(3, $byContext['Indicaciones']);
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
