<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterCapturePersistenceCheck;

class EncounterCapturePersistenceCheckTest extends Unit
{
    public function testUsesConfiguredCategoryTitlesInsteadOfLegacyNames(): void
    {
        $check = new EncounterCapturePersistenceCheck();

        $incomplete = $check->incompleteCategories(
            [
                ['titulo' => 'Tratamientos indicados', 'modelo' => 'EncounterMedication'],
                ['titulo' => 'Intervenciones', 'modelo' => 'EncounterPractice'],
                ['titulo' => 'Razón de atención', 'modelo' => 'EncounterReason'],
            ],
            [
                'EncounterMedication' => ['payload' => true, 'rows' => 1],
                'EncounterPractice' => ['payload' => true, 'rows' => 1],
                'EncounterReason' => ['payload' => true, 'rows' => 1],
            ],
            [
                'medication_requests' => 0,
                'service_requests' => 0,
                'reasons' => false,
            ]
        );

        $this->assertSame(
            ['Tratamientos indicados', 'Intervenciones', 'Razón de atención'],
            $incomplete
        );
    }

    public function testOnlyChecksConfiguredModelsWithRowsAndMissingPersistence(): void
    {
        $check = new EncounterCapturePersistenceCheck();

        $incomplete = $check->incompleteCategories(
            [
                ['titulo' => 'Tratamiento', 'modelo' => 'EncounterMedication'],
                ['titulo' => 'Prácticas', 'modelo' => 'EncounterPractice'],
            ],
            [
                'EncounterMedication' => ['payload' => true, 'rows' => 1],
                'EncounterPractice' => ['payload' => false, 'rows' => 0],
            ],
            ['medication_requests' => 1, 'service_requests' => 0]
        );

        $this->assertSame([], $incomplete);
    }
}
