<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\CareCohort\Presentation\CarePackAssistancePresenter;
use Codeception\Test\Unit;

class CarePackAssistancePresenterTest extends Unit
{
    public function testBuildUiJsonFromPackContent(): void
    {
        $presenter = new CarePackAssistancePresenter();
        $ui = $presenter->buildUiJson(
            [
                'version' => 1,
                'questions' => [
                    [
                        'id' => 'q1',
                        'text' => '¿Tenés fiebre?',
                        'answer_type' => 'choice',
                        'options' => ['Sí', 'No'],
                        'required' => true,
                    ],
                ],
            ],
            42,
            7
        );

        $this->assertSame('ui_definition', $ui['kind']);
        $this->assertSame('ui_json', $ui['ui_type']);
        $fields = $ui['blocks'][0]['fields'] ?? [];
        $this->assertNotEmpty($fields);
        $this->assertSame('q1', $fields[0]['name']);
        $this->assertSame('select', $fields[0]['type']);
    }
}
