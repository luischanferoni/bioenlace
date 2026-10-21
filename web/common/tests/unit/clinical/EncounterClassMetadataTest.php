<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\models\Clinical\EncounterDefinition;

class EncounterClassMetadataTest extends Unit
{
    public function testSessionSelectableClassesExposeTheirCapabilities(): void
    {
        $classes = EncounterDefinition::sessionSelectableClassMetadata();
        $byCode = [];
        foreach ($classes as $class) {
            $byCode[$class['code']] = $class;
        }

        $this->assertSame(
            ['appointments', 'date_navigation'],
            $byCode['AMB']['capabilities']
        );
        $this->assertSame(
            ['emergency_board', 'patient_admission', 'emergency_polling'],
            $byCode['EMER']['capabilities']
        );
        $this->assertSame(
            ['inpatients', 'date_navigation', 'inpatient_refresh'],
            $byCode['IMP']['capabilities']
        );
        $this->assertSame(['async_requests'], $byCode['VR']['capabilities']);
        $this->assertSame('Internación', $byCode['IMP']['label']);
    }

    public function testInactiveKnownClassHasNoProductCapabilities(): void
    {
        $metadata = EncounterDefinition::classMetadata('OBSENC');

        $this->assertSame('OBSENC', $metadata['code']);
        $this->assertSame('Observación', $metadata['label']);
        $this->assertSame([], $metadata['capabilities']);
    }
}
