<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Text\EncounterCaptureExtractionPostProcessPolicy;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

class EncounterCaptureExtractionPostProcessPolicyTest extends Unit
{
    protected function _after(): void
    {
        ClinicalTextIaMetadata::resetCacheForTests();
    }

    public function testDomainDefaultsArePresent(): void
    {
        $filter = EncounterCaptureExtractionPostProcessPolicy::filterConfig();
        $this->assertTrue($filter['enabled']);
        $this->assertContains('ConsultaMotivos', $filter['strict_category_models']);

        $backfill = EncounterCaptureExtractionPostProcessPolicy::backfillConfig();
        $this->assertTrue($backfill['enabled']);
        $this->assertSame(140, $backfill['max_chars']);
        $this->assertNotEmpty($backfill['split_before_patterns']);

        $this->assertSame('ConsultaMotivos', EncounterCaptureExtractionPostProcessPolicy::motivoModel());
    }

    public function testDefaultLexiconMatchesWithoutRelyingOnYamlAlone(): void
    {
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
}
