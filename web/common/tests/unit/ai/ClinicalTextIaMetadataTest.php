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
}
