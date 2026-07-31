<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\ConditionPresentationService;
use common\components\Domain\Clinical\Service\EncounterOpenProblemsService;

class EncounterOpenProblemsServiceTest extends Unit
{
    public function testDedupeKeyMatchesDiagnosisShortLabel(): void
    {
        $presentation = new ConditionPresentationService();
        $fromPrior = $presentation->dedupeKeyForLabel('Cefalea tensional');
        $fromExtraction = $presentation->dedupeKeyForLabel('cefalea tensional');
        $this->assertNotSame('', $fromPrior);
        $this->assertSame($fromPrior, $fromExtraction);
    }

    public function testForCaptureReviewExcludesMatchingDiagnosisViaReflection(): void
    {
        $svc = new EncounterOpenProblemsService();
        $method = new \ReflectionMethod(EncounterOpenProblemsService::class, 'diagnosisDedupeKeysFromExtraction');
        $method->setAccessible(true);

        /** @var array<string, true> $keys */
        $keys = $method->invoke($svc, [
            'Diagnóstico' => [['texto' => 'cefalea tensional']],
            'Medicación' => [['Nombre del medicamento' => 'ibuprofeno']],
        ], [
            ['titulo' => 'Diagnóstico', 'modelo' => 'DiagnosticoConsulta'],
            ['titulo' => 'Medicación', 'modelo' => 'ConsultaMedicamentos'],
        ]);

        $this->assertArrayHasKey('cefalea tensional', $keys);
        $this->assertCount(1, $keys);
    }
}
