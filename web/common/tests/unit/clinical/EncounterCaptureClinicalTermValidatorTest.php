<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Text\EncounterCaptureClinicalTermValidator;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

class EncounterCaptureClinicalTermValidatorTest extends Unit
{
    private EncounterCaptureClinicalTermValidator $validator;

    protected function _before(): void
    {
        $this->validator = new EncounterCaptureClinicalTermValidator();
    }

    protected function _after(): void
    {
        ClinicalTextIaMetadata::resetCacheForTests();
    }

    public function testSubjectiveComplaintIsPlausibleWithoutTerminology(): void
    {
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();
        $config['validate_terminology'] = false;

        $this->assertTrue($this->validator->isPlausibleExtraction('fiebre', 'fiebre', $config));
    }

    public function testArbitraryObjectIsNotPlausibleWithoutTerminology(): void
    {
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();
        $config['validate_terminology'] = false;

        $this->assertFalse($this->validator->isPlausibleExtraction('pelota', 'pelota', $config));
    }

    public function testNarrativeFramingMakesExtractionPlausible(): void
    {
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();
        $config['validate_terminology'] = false;

        $this->assertTrue(
            $this->validator->isPlausibleExtraction(
                'pelota',
                'Paciente refiere pelota en el oído',
                $config
            )
        );
    }

    public function testStructuredRowWithCodeIsAlwaysPlausible(): void
    {
        $config = ['validate_terminology' => false];

        $this->assertTrue(
            $this->validator->isPlausibleExtraction(
                ['texto' => 'pelota', 'codigo_cie10' => 'J00'],
                'pelota',
                $config
            )
        );
    }
}
