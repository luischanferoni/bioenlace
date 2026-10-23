<?php

namespace common\tests\unit\platform\ai;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\Policy\ExtractionPostProcessPolicy;
use common\components\Domain\Clinical\Capture\Application\Service\LlmConfidenceService;
use common\components\Domain\Terminology\Application\Service\SnomedContextualPromptService;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

class ClinicalTextIaMetadataTest extends Unit
{
    protected function _after(): void
    {
        ClinicalTextIaMetadata::resetCacheForTests();
    }

    public function testSnomedPromptIncludesCategoryContext(): void
    {
        $prompt = SnomedContextualPromptService::build('fiebre', 'sintomas');
        $this->assertStringContainsString('fiebre', $prompt);
        $this->assertStringContainsString('síntomas', $prompt);
        $this->assertStringContainsString('SNOMED', $prompt);
    }

    public function testMedicalContextBoostWhenTermPresent(): void
    {
        $boost = LlmConfidenceService::contextBoost('El paciente refiere dolor');
        $this->assertGreaterThan(0.0, $boost);
    }

    public function testMedicalContextBoostZeroWhenNoTerms(): void
    {
        $this->assertSame(0.0, LlmConfidenceService::contextBoost('texto neutro sin vocabulario'));
    }

    public function testEncounterCapturePromptUsesDynamicCategorySemantics(): void
    {
        $categorias = [
            ['titulo' => 'Motivos de consulta', 'modelo' => 'EncounterReason'],
            ['titulo' => 'Diagnóstico', 'modelo' => 'EncounterDiagnosis'],
        ];

        $prompt = ClinicalTextIaMetadata::buildEncounterCaptureExtractionPrompt('gripe', $categorias);

        $this->assertStringContainsString('gripe', $prompt);
        $this->assertStringContainsString('Motivos de consulta', $prompt);
        $this->assertStringContainsString('Diagnóstico', $prompt);
        $this->assertStringContainsString('queja o síntoma referido', $prompt);
        $this->assertStringContainsString('diagnóstico, evolución o enfermedad', $prompt);
        $this->assertStringNotContainsString('NO uses esta categoría para nombres de enfermedades', $prompt);
        $this->assertStringNotContainsString('{abbreviations_block}', $prompt);
        $this->assertStringNotContainsString('Abreviaturas ambiguas', $prompt);
    }

    public function testEncounterCapturePromptIncludesAmbiguousAbbreviations(): void
    {
        $prompt = ClinicalTextIaMetadata::buildEncounterCaptureExtractionPrompt(
            'paciente con IC',
            [['titulo' => 'Diagnóstico', 'modelo' => 'EncounterDiagnosis']],
            '',
            ['IC' => ['insuficiencia cardíaca', 'intervalo de confianza']]
        );

        $this->assertStringContainsString('Abreviaturas ambiguas', $prompt);
        $this->assertStringContainsString('- IC: insuficiencia cardíaca | intervalo de confianza', $prompt);
    }

    public function testVeterinaryCapturePromptUsesOwnTemplateWithSameContract(): void
    {
        $categorias = [['titulo' => 'Motivos de consulta', 'modelo' => 'X']];

        $vet = ClinicalTextIaMetadata::buildEncounterCaptureExtractionPrompt('no come', $categorias, '', [], 'veterinario');
        $human = ClinicalTextIaMetadata::buildEncounterCaptureExtractionPrompt('no come', $categorias);

        $this->assertStringContainsString('veterinarios', $vet);
        $this->assertStringContainsString('mg/kg', $vet);
        $this->assertStringNotContainsString('veterinarios', $human);
        foreach ([$vet, $human] as $prompt) {
            $this->assertStringContainsString('texto_procesado', $prompt);
            $this->assertStringContainsString('datosExtraidos', $prompt);
            $this->assertStringNotContainsString('{clinical_text}', $prompt);
            $this->assertStringNotContainsString('{abbreviations_block}', $prompt);
        }
    }

    public function testClinicalLexiconMatchesNarrativeFraming(): void
    {
        $this->assertTrue(
            ExtractionPostProcessPolicy::textMatchesClinicalLexiconPattern(
                'Paciente refiere fiebre',
                'narrative_framing'
            )
        );
        $this->assertFalse(
            ExtractionPostProcessPolicy::textMatchesClinicalLexiconPattern('gripe', 'narrative_framing')
        );
    }

    public function testClinicalLexiconMatchesSubjectiveComplaintCefalea(): void
    {
        $this->assertTrue(
            ExtractionPostProcessPolicy::textMatchesClinicalLexiconPattern(
                'Cefalea tensional de una semana, sin signos de alarma.',
                'subjective_complaint'
            )
        );
    }

    public function testNormalizePregPatternRewritesInvalidInlineUnicodeFlag(): void
    {
        $normalized = ClinicalTextIaMetadata::normalizePregPattern('(?iu)\bcefalea\b');
        $this->assertSame('/\bcefalea\b/iu', $normalized);
        $this->assertSame(1, preg_match($normalized, 'Cefalea'));
    }
}
