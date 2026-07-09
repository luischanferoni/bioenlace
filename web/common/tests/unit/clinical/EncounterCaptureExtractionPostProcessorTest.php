<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Text\EncounterCaptureClinicalTermValidator;
use common\components\Domain\Clinical\Text\EncounterCaptureExtractionPostProcessor;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

class EncounterCaptureExtractionPostProcessorTest extends Unit
{
    private const CATEGORIAS_AMB = [
        ['titulo' => 'Motivos de consulta', 'modelo' => 'ConsultaMotivos', 'requerido' => false],
        ['titulo' => 'Diagnóstico', 'modelo' => 'DiagnosticoConsulta', 'requerido' => false],
    ];

    protected function _after(): void
    {
        ClinicalTextIaMetadata::resetCacheForTests();
    }

    public function testDiscardsNonClinicalIsolatedTerm(): void
    {
        $validator = $this->validatorMock(
            plausibleExtraction: false,
            plausibleDiagnosis: false,
            isolatedDiagnosis: false
        );

        $processor = new EncounterCaptureExtractionPostProcessor($validator);
        $input = [
            'datosExtraidos' => [
                'Motivos de consulta' => ['pelota'],
                'Diagnóstico' => [],
            ],
        ];

        $out = $processor->apply($input, self::CATEGORIAS_AMB, 'pelota');

        $this->assertSame([], $out['datosExtraidos']['Motivos de consulta']);
        $this->assertSame([], $out['datosExtraidos']['Diagnóstico']);
    }

    public function testKeepsGripeInDiagnostico(): void
    {
        $validator = $this->validatorMock(
            plausibleExtraction: false,
            plausibleDiagnosis: true,
            isolatedDiagnosis: false
        );

        $processor = new EncounterCaptureExtractionPostProcessor($validator);
        $input = [
            'datosExtraidos' => [
                'Motivos de consulta' => [],
                'Diagnóstico' => ['gripe'],
            ],
        ];

        $out = $processor->apply($input, self::CATEGORIAS_AMB, 'gripe');

        $this->assertSame(['gripe'], $out['datosExtraidos']['Diagnóstico']);
        $this->assertSame([], $out['datosExtraidos']['Motivos de consulta']);
    }

    public function testKeepsMotivoWhenNarrativeFramingPresent(): void
    {
        $validator = $this->validatorMock(true, true, false);

        $processor = new EncounterCaptureExtractionPostProcessor($validator);
        $input = [
            'datosExtraidos' => [
                'Motivos de consulta' => ['fiebre'],
                'Diagnóstico' => [],
            ],
        ];

        $out = $processor->apply(
            $input,
            self::CATEGORIAS_AMB,
            'Paciente refiere fiebre desde hace dos días'
        );
        $this->assertSame(['fiebre'], $out['datosExtraidos']['Motivos de consulta']);
        $this->assertSame([], $out['datosExtraidos']['Diagnóstico']);
    }

    public function testKeepsIsolatedSubjectiveComplaintInMotivo(): void
    {
        $validator = $this->validatorMock(true, true, false);

        $processor = new EncounterCaptureExtractionPostProcessor($validator);
        $input = [
            'datosExtraidos' => [
                'Motivos de consulta' => ['fiebre'],
                'Diagnóstico' => [],
            ],
        ];

        $out = $processor->apply($input, self::CATEGORIAS_AMB, 'fiebre');

        $this->assertSame(['fiebre'], $out['datosExtraidos']['Motivos de consulta']);
        $this->assertSame([], $out['datosExtraidos']['Diagnóstico']);
    }

    public function testDoesNotRelocateWhenTextExceedsMaxWords(): void
    {
        $validator = $this->validatorMock(true, true, false);

        $processor = new EncounterCaptureExtractionPostProcessor($validator);
        $clinicalText = 'cuadro compatible con gripe estacional sin complicaciones respiratorias';
        $input = [
            'datosExtraidos' => [
                'Motivos de consulta' => [$clinicalText],
                'Diagnóstico' => [],
            ],
        ];

        $out = $processor->apply($input, self::CATEGORIAS_AMB, $clinicalText);

        $this->assertSame([$clinicalText], $out['datosExtraidos']['Motivos de consulta']);
        $this->assertSame([], $out['datosExtraidos']['Diagnóstico']);
    }

    public function testDoesNotMoveWhenDiagnosticoAlreadyHasItems(): void
    {
        $validator = $this->validatorMock(true, true, true);

        $processor = new EncounterCaptureExtractionPostProcessor($validator);
        $input = [
            'datosExtraidos' => [
                'Motivos de consulta' => ['gripe'],
                'Diagnóstico' => ['neumonía'],
            ],
        ];

        $out = $processor->apply($input, self::CATEGORIAS_AMB, 'gripe');

        $this->assertSame(['gripe'], $out['datosExtraidos']['Motivos de consulta']);
        $this->assertSame(['neumonía'], $out['datosExtraidos']['Diagnóstico']);
    }

    public function testDoesNotRelocateMultipleMotivoItems(): void
    {
        $validator = $this->validatorMock(true, true, true);

        $processor = new EncounterCaptureExtractionPostProcessor($validator);
        $input = [
            'datosExtraidos' => [
                'Motivos de consulta' => ['gripe', 'fiebre'],
                'Diagnóstico' => [],
            ],
        ];

        $out = $processor->apply($input, self::CATEGORIAS_AMB, 'gripe');

        $this->assertSame(['gripe', 'fiebre'], $out['datosExtraidos']['Motivos de consulta']);
        $this->assertSame([], $out['datosExtraidos']['Diagnóstico']);
    }

    /**
     * @return EncounterCaptureClinicalTermValidator&\PHPUnit\Framework\MockObject\MockObject
     */
    private function validatorMock(
        bool $plausibleExtraction,
        bool $plausibleDiagnosis,
        bool $isolatedDiagnosis
    ) {
        $validator = $this->createMock(EncounterCaptureClinicalTermValidator::class);
        $validator->method('isPlausibleExtraction')->willReturn($plausibleExtraction);
        $validator->method('isPlausibleDiagnosisExtraction')->willReturn($plausibleDiagnosis);
        $validator->method('isPlausibleIsolatedDiagnosisCandidate')->willReturn($isolatedDiagnosis);

        return $validator;
    }
}
