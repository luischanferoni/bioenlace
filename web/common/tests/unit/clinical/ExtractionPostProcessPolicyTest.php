<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\Policy\ExtractionPostProcessPolicy;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

class ExtractionPostProcessPolicyTest extends Unit
{
    protected function _after(): void
    {
        ClinicalTextIaMetadata::resetCacheForTests();
    }

    public function testDomainDefaultsArePresent(): void
    {
        ExtractionPostProcessPolicy::resetAllForTests();

        $filter = ExtractionPostProcessPolicy::filterConfig();
        $this->assertTrue($filter['enabled']);
        $this->assertContains('EncounterReason', $filter['strict_category_models']);

        $backfill = ExtractionPostProcessPolicy::backfillConfig();
        $this->assertTrue($backfill['enabled']);
        $this->assertSame(140, $backfill['max_chars']);
        $this->assertNotEmpty($backfill['split_before_patterns']);

        $this->assertSame('EncounterReason', ExtractionPostProcessPolicy::reasonModel());
    }

    public function testDefaultLexiconMatchesWithoutRelyingOnYamlAlone(): void
    {
        ExtractionPostProcessPolicy::resetAllForTests();

        $this->assertTrue(
            ExtractionPostProcessPolicy::textMatchesClinicalLexiconPattern(
                'Paciente refiere fiebre',
                'narrative_framing'
            )
        );
        $this->assertTrue(
            ExtractionPostProcessPolicy::textMatchesClinicalLexiconPattern(
                'Cefalea tensional',
                'subjective_complaint'
            )
        );
    }

    public function testConfigureInjectsOverridesWithoutPlatformImport(): void
    {
        ExtractionPostProcessPolicy::configure(
            [
                'reason_model' => 'CustomReason',
                'filter_non_clinical_extractions' => ['enabled' => false],
            ],
            []
        );

        $this->assertSame('CustomReason', ExtractionPostProcessPolicy::reasonModel());
        $this->assertFalse(ExtractionPostProcessPolicy::filterConfig()['enabled']);
    }
}
