<?php

namespace common\tests\unit\clinical;

use common\components\Clinical\CareCohort\Presentation\CarePackFollowupPresenter;
use Codeception\Test\Unit;

class CarePackFollowupPresenterTest extends Unit
{
    public function testBuildUiJsonWithEducationAndEvolutionFields(): void
    {
        $presenter = new CarePackFollowupPresenter();
        $ui = $presenter->buildUiJson(
            [
                'title' => 'Control a los 3 días',
                'purpose' => 'evolution',
                'form_kind' => 'evolution_short',
                'touchpoint_key' => 'tp-0',
            ],
            11,
            42,
            [
                [
                    'id' => 'm1',
                    'title' => 'Reposo',
                    'summary' => 'Descansá las primeras 48 h.',
                    'bullet_points' => ['Evitá esfuerzo'],
                    'when_to_seek_care' => 'Fiebre alta',
                ],
            ]
        );

        $this->assertSame('ui_definition', $ui['kind']);
        $this->assertSame('Control a los 3 días', $ui['title']);
        $this->assertSame('message', $ui['blocks'][0]['kind']);
        $fieldsBlock = $ui['blocks'][1];
        $this->assertSame('fields', $fieldsBlock['kind']);
        $this->assertSame('sintomas_evolucion', $fieldsBlock['fields'][0]['name']);
    }
}
