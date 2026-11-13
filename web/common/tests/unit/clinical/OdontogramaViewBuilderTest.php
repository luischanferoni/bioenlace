<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use frontend\components\Clinical\OdontogramaViewBuilder;

/**
 * View model del odontograma: agrupado, etiquetas y clases por momento.
 */
class OdontogramaViewBuilderTest extends Unit
{
    public function testEmptyOdontogramIsNotVisible(): void
    {
        $this->assertFalse(OdontogramaViewBuilder::build(null)['visible']);
        $this->assertFalse(OdontogramaViewBuilder::build([])['visible']);
        $this->assertFalse(OdontogramaViewBuilder::build(['teeth' => [], 'totals' => []])['visible']);
    }

    public function testRowsFollowDentitionOrderAndSkipEmptyGroups(): void
    {
        $chart = [
            'teeth' => [
                ['tooth' => '51', 'dentition' => 'temporary', 'current' => ['codigo' => 'caries'], 'history' => []],
                ['tooth' => '16', 'dentition' => 'permanent', 'current' => ['codigo' => 'obturada'], 'history' => []],
                ['tooth' => '99', 'dentition' => null, 'current' => ['codigo' => 'otro'], 'history' => []],
            ],
            'totals' => ['teeth' => 3, 'states' => 3, 'unassigned' => 0],
        ];

        $view = OdontogramaViewBuilder::build($chart);

        $this->assertTrue($view['visible']);
        $this->assertSame(
            ['Dentición permanente', 'Dentición temporaria', 'Sin dentición clasificada'],
            array_column($view['rows'], 'title')
        );
        $this->assertSame(['16'], array_column($view['rows'][0]['teeth'], 'number'));
        $this->assertSame(['51'], array_column($view['rows'][1]['teeth'], 'number'));
        $this->assertSame(['99'], array_column($view['rows'][2]['teeth'], 'number'));
        $this->assertSame(['teeth' => 3, 'states' => 3, 'unassigned' => 0], $view['totals']);
    }

    public function testBadgeReflectsTimeQualifier(): void
    {
        $chart = [
            'teeth' => [
                ['tooth' => '11', 'dentition' => 'permanent', 'current' => ['codigo' => 'a', 'tiempo' => 'PASADA'], 'history' => []],
                ['tooth' => '12', 'dentition' => 'permanent', 'current' => ['codigo' => 'b', 'tiempo' => 'PRESENTE'], 'history' => []],
                ['tooth' => '13', 'dentition' => 'permanent', 'current' => ['codigo' => 'c', 'tiempo' => 'FUTURA'], 'history' => []],
                ['tooth' => '14', 'dentition' => 'permanent', 'current' => ['codigo' => 'd'], 'history' => []],
            ],
        ];

        $teeth = OdontogramaViewBuilder::build($chart)['rows'][0]['teeth'];

        $this->assertSame(['Pasada', 'Presente', 'Futura', 'Sin momento'], array_column($teeth, 'badgeLabel'));
        $this->assertSame(
            ['text-bg-secondary', 'text-bg-primary', 'text-bg-info', 'text-bg-light'],
            array_column($teeth, 'badgeClass')
        );
    }

    public function testLabelPrefersDisplayThenCodeThenType(): void
    {
        $chart = [
            'teeth' => [
                ['tooth' => '11', 'dentition' => 'permanent', 'current' => ['display' => 'Caries oclusal', 'codigo' => '33070004'], 'history' => []],
                ['tooth' => '12', 'dentition' => 'permanent', 'current' => ['codigo' => 'ausente', 'tipo' => 'Estado'], 'history' => []],
                ['tooth' => '13', 'dentition' => 'permanent', 'current' => ['tipo' => 'Estado'], 'history' => []],
                ['tooth' => '14', 'dentition' => 'permanent', 'current' => [], 'history' => []],
            ],
        ];

        $teeth = OdontogramaViewBuilder::build($chart)['rows'][0]['teeth'];

        $this->assertSame(
            ['Caries oclusal', 'ausente', 'Estado', 'sin detalle'],
            array_column($teeth, 'label')
        );
    }

    public function testHistoryKeepsSurfacesAndShortensDate(): void
    {
        $chart = [
            'teeth' => [
                [
                    'tooth' => '16',
                    'dentition' => 'permanent',
                    'current' => ['codigo' => 'obturada', 'tiempo' => 'PRESENTE'],
                    'history' => [
                        ['codigo' => '33070004', 'caras' => 'O', 'tiempo' => 'PASADA', 'registradoEn' => '2026-11-13 16:52:05'],
                        ['codigo' => 'obturada', 'caras' => null, 'tiempo' => 'PRESENTE', 'registradoEn' => '2026-12-01 09:00:00'],
                    ],
                ],
            ],
        ];

        $history = OdontogramaViewBuilder::build($chart)['rows'][0]['teeth'][0]['history'];

        $this->assertSame(['2026-11-13 16:52', '2026-12-01 09:00'], array_column($history, 'when'));
        $this->assertSame(['O', ''], array_column($history, 'surfaces'));
        $this->assertSame(['Pasada', 'Presente'], array_column($history, 'badgeLabel'));
    }

    public function testTargetIdIsSafeForHtml(): void
    {
        $chart = [
            'teeth' => [
                ['tooth' => '1 6', 'dentition' => 'permanent', 'current' => ['codigo' => 'x'], 'history' => []],
            ],
        ];

        $tooth = OdontogramaViewBuilder::build($chart)['rows'][0]['teeth'][0];

        $this->assertSame('odonto-16', $tooth['target']);
        $this->assertSame('1 6', $tooth['number']);
    }
}
