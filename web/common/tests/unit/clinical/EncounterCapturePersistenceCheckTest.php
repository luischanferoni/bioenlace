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
                ['titulo' => 'Tratamientos indicados', 'modelo' => 'ConsultaMedicamentos'],
                ['titulo' => 'Intervenciones', 'modelo' => 'ConsultaPracticas'],
                ['titulo' => 'Razón de atención', 'modelo' => 'EncounterReason'],
            ],
            [
                'ConsultaMedicamentos' => ['payload' => true, 'rows' => 1],
                'ConsultaPracticas' => ['payload' => true, 'rows' => 1],
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
                ['titulo' => 'Tratamiento', 'modelo' => 'ConsultaMedicamentos'],
                ['titulo' => 'Prácticas', 'modelo' => 'ConsultaPracticas'],
            ],
            [
                'ConsultaMedicamentos' => ['payload' => true, 'rows' => 1],
                'ConsultaPracticas' => ['payload' => false, 'rows' => 0],
            ],
            ['medication_requests' => 1, 'service_requests' => 0]
        );

        $this->assertSame([], $incomplete);
    }
}
