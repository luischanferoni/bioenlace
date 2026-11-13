<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use frontend\components\Clinical\RecetaLentesViewBuilder;

/**
 * View model de la receta de lentes.
 */
class RecetaLentesViewBuilderTest extends Unit
{
    public function testWithoutPrescriptionIsNotVisible(): void
    {
        $this->assertFalse(RecetaLentesViewBuilder::build(null)['visible']);
        $this->assertFalse(RecetaLentesViewBuilder::build([
            'od' => ['esfera' => null, 'cilindro' => null, 'eje' => null, 'adicion' => null],
            'oi' => ['esfera' => null, 'cilindro' => null, 'eje' => null, 'adicion' => null],
        ])['visible']);
    }

    public function testRowsFollowPrescriptionOrder(): void
    {
        $view = RecetaLentesViewBuilder::build([
            'registradoEn' => '2026-11-13 16:52:05',
            'od' => ['esfera' => '-1.25', 'cilindro' => '-0.50', 'eje' => '90', 'adicion' => '+1.00'],
            'oi' => ['esfera' => '-0.75', 'cilindro' => null, 'eje' => '80', 'adicion' => null],
        ]);

        $this->assertTrue($view['visible']);
        $this->assertSame('2026-11-13', $view['registradoEn']);
        $this->assertSame(['Esfera', 'Cilindro', 'Eje', 'Adición'], array_column($view['rows'], 'label'));
        $this->assertSame(['-1.25', '-0.50', '90', '+1.00'], array_column($view['rows'], 'od'));
        $this->assertSame(['-0.75', '', '80', ''], array_column($view['rows'], 'oi'));
    }

    public function testOneEyeIsEnoughToBeVisible(): void
    {
        $view = RecetaLentesViewBuilder::build([
            'od' => ['esfera' => null, 'cilindro' => null, 'eje' => null, 'adicion' => null],
            'oi' => ['esfera' => '+2.00', 'cilindro' => null, 'eje' => null, 'adicion' => null],
        ]);

        $this->assertTrue($view['visible']);
        $this->assertSame('', $view['registradoEn']);
        $this->assertSame(['', '', '', ''], array_column($view['rows'], 'od'));
    }
}
