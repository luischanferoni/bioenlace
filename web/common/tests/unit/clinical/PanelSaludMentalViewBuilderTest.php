<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use frontend\components\Clinical\PanelSaludMentalViewBuilder;

/**
 * View model del panel de salud mental.
 */
class PanelSaludMentalViewBuilderTest extends Unit
{
    public function testWithoutSessionsIsNotVisible(): void
    {
        $this->assertFalse(PanelSaludMentalViewBuilder::build([])['visible']);
    }

    public function testScoresCarrySeverityAndTextsAreSeparated(): void
    {
        $view = PanelSaludMentalViewBuilder::build([
            [
                'panelId' => 3,
                'registradoEn' => '2026-11-14 09:30:00',
                'values' => [
                    'Motivo de sesion' => ['label' => 'Motivo de sesion', 'value' => 'Insomnio', 'unit' => null],
                    'PHQ-9' => ['label' => 'PHQ-9', 'value' => '12', 'unit' => 'pts'],
                ],
                'scores' => [
                    [
                        'code' => 'phq9',
                        'label' => 'PHQ-9',
                        'value' => 12.0,
                        'unit' => 'pts',
                        'severity' => ['label' => 'Moderada', 'intent' => 'warning'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($view['visible']);
        $sesion = $view['sesiones'][0];

        $this->assertSame('2026-11-14', $sesion['fecha']);
        $this->assertCount(1, $sesion['scores']);
        $this->assertSame('PHQ-9', $sesion['scores'][0]['label']);
        $this->assertSame('12', $sesion['scores'][0]['value']);
        $this->assertSame('Moderada', $sesion['scores'][0]['severityLabel']);
        $this->assertSame('text-bg-warning', $sesion['scores'][0]['severityClass']);

        // El puntaje no se repite como texto de sesión.
        $this->assertSame(
            [['label' => 'Motivo de sesion', 'value' => 'Insomnio']],
            $sesion['texts']
        );
    }

    public function testScoreWithoutSeverityStillRenders(): void
    {
        $view = PanelSaludMentalViewBuilder::build([
            [
                'panelId' => 1,
                'registradoEn' => '',
                'values' => [],
                'scores' => [[
                    'code' => 'gad7',
                    'label' => 'GAD-7',
                    'value' => 3.0,
                    'unit' => 'pts',
                    'severity' => null,
                ]],
            ],
        ]);

        $this->assertTrue($view['visible']);
        $this->assertSame('', $view['sesiones'][0]['scores'][0]['severityLabel']);
        $this->assertSame('', $view['sesiones'][0]['scores'][0]['severityClass']);
        $this->assertSame('', $view['sesiones'][0]['fecha']);
    }

    public function testSessionWithoutContentIsSkipped(): void
    {
        $view = PanelSaludMentalViewBuilder::build([
            ['panelId' => 1, 'registradoEn' => '2026-11-14 09:00:00', 'values' => [], 'scores' => []],
        ]);

        $this->assertFalse($view['visible']);
    }
}
