<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Text\EncounterCaptureClinicalTermValidator;
use common\components\Domain\Clinical\Text\EncounterCaptureTerminologyLookup;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

class EncounterCaptureClinicalTermValidatorTest extends Unit
{
    protected function _after(): void
    {
        ClinicalTextIaMetadata::resetCacheForTests();
    }

    public function testSubjectiveComplaintIsPlausibleWithoutTerminology(): void
    {
        $lookup = $this->createMock(EncounterCaptureTerminologyLookup::class);
        $lookup->expects($this->never())->method('matchesClinicalTerm');

        $validator = new EncounterCaptureClinicalTermValidator($lookup);
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();
        $config['validate_terminology'] = false;

        $this->assertTrue($validator->isPlausibleExtraction('fiebre', 'fiebre', $config));
    }

    public function testArbitraryObjectIsNotPlausibleWithoutTerminology(): void
    {
        $lookup = $this->createMock(EncounterCaptureTerminologyLookup::class);
        $lookup->method('matchesClinicalTerm')->willReturn(false);

        $validator = new EncounterCaptureClinicalTermValidator($lookup);
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();

        $this->assertFalse($validator->isPlausibleExtraction('pelota', 'pelota', $config));
        $this->assertFalse($validator->isPlausibleDiagnosisExtraction('pelota', 'pelota', $config));
    }

    public function testDiagnosisExtractionRequiresTerminology(): void
    {
        $lookup = $this->createMock(EncounterCaptureTerminologyLookup::class);
        $lookup->method('matchesClinicalTerm')
            ->willReturnCallback(static fn (string $term): bool => $term === 'gripe');
        $lookup->method('wasTerminologyServiceUnavailable')->willReturn(false);

        $validator = new EncounterCaptureClinicalTermValidator($lookup);
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();

        $this->assertTrue($validator->isPlausibleDiagnosisExtraction('gripe', 'gripe', $config));
        $this->assertFalse($validator->isPlausibleDiagnosisExtraction('pelota', 'pelota', $config));
    }

    public function testDiagnosisTrustsIaWhenTerminologyDisabled(): void
    {
        $lookup = $this->createMock(EncounterCaptureTerminologyLookup::class);
        $lookup->expects($this->never())->method('matchesClinicalTerm');

        $validator = new EncounterCaptureClinicalTermValidator($lookup);
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();

        $this->assertTrue($validator->isPlausibleDiagnosisExtraction('gripe', 'gripe', $config));
        $this->assertTrue($validator->isPlausibleDiagnosisExtraction('pelota', 'pelota', $config));
    }

    public function testNarrativeFramingMakesExtractionPlausible(): void
    {
        $lookup = $this->createMock(EncounterCaptureTerminologyLookup::class);
        $lookup->expects($this->never())->method('matchesClinicalTerm');

        $validator = new EncounterCaptureClinicalTermValidator($lookup);
        $config = ClinicalTextIaMetadata::encounterCaptureFilterConfig();
        $config['validate_terminology'] = false;

        $this->assertTrue(
            $validator->isPlausibleExtraction(
                'pelota',
                'Paciente refiere pelota en el oído',
                $config
            )
        );
    }
}
