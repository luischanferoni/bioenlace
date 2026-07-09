<?php

namespace common\tests\unit\ai;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Text\MedicalLlmConfidenceService;
use common\components\Domain\Terminology\Snomed\SnomedContextualPromptBuilder;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

class ClinicalTextIaMetadataTest extends Unit
{
    protected function _after(): void
    {
        ClinicalTextIaMetadata::resetCacheForTests();
    }

    public function testSnomedPromptIncludesCategoryContext(): void
    {
        $prompt = SnomedContextualPromptBuilder::build('fiebre', 'sintomas');
        $this->assertStringContainsString('fiebre', $prompt);
        $this->assertStringContainsString('síntomas', $prompt);
        $this->assertStringContainsString('SNOMED', $prompt);
    }

    public function testMedicalContextBoostWhenTermPresent(): void
    {
        $boost = MedicalLlmConfidenceService::contextBoost('El paciente refiere dolor');
        $this->assertGreaterThan(0.0, $boost);
    }

    public function testMedicalContextBoostZeroWhenNoTerms(): void
    {
        $this->assertSame(0.0, MedicalLlmConfidenceService::contextBoost('texto neutro sin vocabulario'));
    }

    public function testEncounterCapturePromptUsesDynamicCategorySemantics(): void
    {
        $categorias = [
            ['titulo' => 'Motivos de consulta', 'modelo' => 'ConsultaMotivos'],
            ['titulo' => 'Diagnóstico', 'modelo' => 'DiagnosticoConsulta'],
        ];

        $prompt = ClinicalTextIaMetadata::buildEncounterCaptureExtractionPrompt('gripe', $categorias);

        $this->assertStringContainsString('gripe', $prompt);
        $this->assertStringContainsString('Motivos de consulta', $prompt);
        $this->assertStringContainsString('Diagnóstico', $prompt);
        $this->assertStringContainsString('queja o síntoma referido', $prompt);
        $this->assertStringContainsString('diagnóstico, evolución o enfermedad', $prompt);
        $this->assertStringNotContainsString('NO uses esta categoría para nombres de enfermedades', $prompt);
    }

    public function testClinicalLexiconMatchesNarrativeFraming(): void
    {
        $this->assertTrue(
            ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern(
                'Paciente refiere fiebre',
                'narrative_framing'
            )
        );
        $this->assertFalse(
            ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern('gripe', 'narrative_framing')
        );
    }
}
