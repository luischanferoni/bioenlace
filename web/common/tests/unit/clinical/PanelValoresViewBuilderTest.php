<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use frontend\components\Clinical\PanelValoresViewBuilder;

/**
 * View model de los paneles de valores con unidades (prenatal, nutrición, kinesiología).
 */
class PanelValoresViewBuilderTest extends Unit
{
    public function testWithoutPanelsIsNotVisible(): void
    {
        $this->assertFalse(PanelValoresViewBuilder::build([])['visible']);
    }

    public function testPanelsAreRenderedWithUnits(): void
    {
        $view = PanelValoresViewBuilder::build([
            [
                'panelId' => 2,
                'registradoEn' => '2026-11-14 10:00:00',
                'encounterId' => 9,
                'values' => [
                    'Edad gestacional' => ['label' => 'Edad gestacional', 'value' => '28', 'unit' => 'sem'],
                    'Altura uterina' => ['label' => 'Altura uterina', 'value' => '29.5', 'unit' => 'cm'],
                    'Presentacion' => ['label' => 'Presentacion', 'value' => 'cefalica', 'unit' => null],
                ],
            ],
        ]);

        $this->assertTrue($view['visible']);
        $this->assertCount(1, $view['paneles']);
        $panel = $view['paneles'][0];

        $this->assertSame('2026-11-14', $panel['fecha']);
        $this->assertSame(
            ['Edad gestacional', 'Altura uterina', 'Presentacion'],
            array_column($panel['rows'], 'label')
        );
        $this->assertSame(['sem', 'cm', ''], array_column($panel['rows'], 'unit'));
        $this->assertSame(['28', '29.5', 'cefalica'], array_column($panel['rows'], 'value'));
    }

    public function testPanelWithoutValuesIsSkipped(): void
    {
        $view = PanelValoresViewBuilder::build([
            ['panelId' => 1, 'registradoEn' => '2026-11-14 10:00:00', 'values' => []],
            [
                'panelId' => 2,
                'registradoEn' => '2026-11-15 10:00:00',
                'values' => ['FUM' => ['label' => 'FUM', 'value' => '2026-03-10', 'unit' => null]],
            ],
        ]);

        $this->assertCount(1, $view['paneles']);
        $this->assertSame('2026-11-15', $view['paneles'][0]['fecha']);
    }

    public function testBlankValuesAreDropped(): void
    {
        $view = PanelValoresViewBuilder::build([
            [
                'panelId' => 3,
                'registradoEn' => '2026-11-16 09:00:00',
                'values' => [
                    'IMC' => ['label' => 'IMC', 'value' => '27.4', 'unit' => 'kg/m2'],
                    'Plan alimentario' => ['label' => 'Plan alimentario', 'value' => '   ', 'unit' => null],
                ],
            ],
        ]);

        $this->assertSame(['IMC'], array_column($view['paneles'][0]['rows'], 'label'));
    }

    public function testPanelWithoutRegularDateKeepsEmptyDate(): void
    {
        $view = PanelValoresViewBuilder::build([
            ['panelId' => 4, 'registradoEn' => '', 'values' => [
                'Dolor EVA' => ['label' => 'Dolor EVA', 'value' => '6', 'unit' => '/10'],
            ]],
        ]);

        $this->assertSame('', $view['paneles'][0]['fecha']);
        $this->assertTrue($view['visible']);
    }
}