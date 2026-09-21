<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\Policy\EncounterCaptureExtractionPostProcessPolicy;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

class EncounterCaptureExtractionPostProcessPolicyTest extends Unit
{
    protected function _after(): void
    {
        ClinicalTextIaMetadata::resetCacheForTests();
    }

    public function testDomainDefaultsArePresent(): void
    {
        EncounterCaptureExtractionPostProcessPolicy::resetAllForTests();

        $filter = EncounterCaptureExtractionPostProcessPolicy::filterConfig();
        $this->assertTrue($filter['enabled']);
        $this->assertContains('EncounterReason', $filter['strict_category_models']);

        $backfill = EncounterCaptureExtractionPostProcessPolicy::backfillConfig();
        $this->assertTrue($backfill['enabled']);
        $this->assertSame(140, $backfill['max_chars']);
        $this->assertNotEmpty($backfill['split_before_patterns']);

        $this->assertSame('EncounterReason', EncounterCaptureExtractionPostProcessPolicy::reasonModel());
    }

    public function testDefaultLexiconMatchesWithoutRelyingOnYamlAlone(): void
    {
        EncounterCaptureExtractionPostProcessPolicy::resetAllForTests();

        $this->assertTrue(
            EncounterCaptureExtractionPostProcessPolicy::textMatchesClinicalLexiconPattern(
                'Paciente refiere fiebre',
                'narrative_framing'
            )
        );
        $this->assertTrue(
            EncounterCaptureExtractionPostProcessPolicy::textMatchesClinicalLexiconPattern(
                'Cefalea tensional',
                'subjective_complaint'
            )
        );
    }

    public function testConfigureInjectsOverridesWithoutPlatformImport(): void
    {
        EncounterCaptureExtractionPostProcessPolicy::configure(
            [
                'reason_model' => 'CustomReason',
                'filter_non_clinical_extractions' => ['enabled' => false],
            ],
            []
        );

        $this->assertSame('CustomReason', EncounterCaptureExtractionPostProcessPolicy::reasonModel());
        $this->assertFalse(EncounterCaptureExtractionPostProcessPolicy::filterConfig()['enabled']);
    }
}
