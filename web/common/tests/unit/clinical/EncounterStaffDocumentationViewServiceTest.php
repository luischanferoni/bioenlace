<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EncounterStaffDocumentationViewService;

class EncounterStaffDocumentationViewServiceTest extends Unit
{
    public function testSectionPayloadPublishesGenericFieldMetadataAndLegacyItems(): void
    {
        $section = EncounterStaffDocumentationViewService::sectionPayload(
            'diagnoses',
            'Diagnósticos',
            [' Hipertensión ', '', 'Diabetes']
        );

        $this->assertSame('diagnoses', $section['key']);
        $this->assertSame('Diagnósticos', $section['label']);
        $this->assertSame(['Hipertensión', 'Diabetes'], $section['items']);
        $this->assertSame(
            [
                [
                    'key' => 'item_1',
                    'label' => 'Detalle',
                    'value' => 'Hipertensión',
                    'unit' => null,
                    'format' => 'text',
                ],
                [
                    'key' => 'item_2',
                    'label' => 'Detalle',
                    'value' => 'Diabetes',
                    'unit' => null,
                    'format' => 'text',
                ],
            ],
            $section['fields']
        );
    }
}
