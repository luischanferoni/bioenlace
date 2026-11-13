<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract;
use common\components\Domain\Clinical\Specialty\Application\Service\OdontologyEncounterService;

/**
 * Contrato de estado dental previo a la persistencia como observación.
 */
class OdontologyToothStateTest extends Unit
{
    public function testToothStateFieldsNormalizesDentalKeys(): void
    {
        $fields = OdontologyEncounterService::toothStateFields([
            'Codigo' => '33070004',
            'Tipo' => 'Carie',
            'Pieza' => '16',
            'Caras' => 'O',
            'Tiempo' => 'pasada',
            'termino' => 'Caries oclusal',
        ]);

        $this->assertSame([
            'pieza' => '16',
            'codigo' => '33070004',
            'caras' => 'O',
            'tipo' => 'Carie',
            'termino' => 'Caries oclusal',
            'tiempo' => 'PASADA',
        ], $fields);
    }

    public function testToothStateFieldsReturnsNullWithoutCodigo(): void
    {
        $this->assertNull(OdontologyEncounterService::toothStateFields(['Pieza' => '21', 'Caras' => 'M']));
        $this->assertNull(OdontologyEncounterService::toothStateFields(['Codigo' => '   ']));
    }

    public function testToothStateFieldsKeepsOptionalDataNull(): void
    {
        $fields = OdontologyEncounterService::toothStateFields(['Codigo' => 'ausente']);

        $this->assertNotNull($fields);
        $this->assertSame('ausente', $fields['codigo']);
        $this->assertNull($fields['pieza']);
        $this->assertNull($fields['caras']);
        $this->assertNull($fields['tipo']);
        $this->assertNull($fields['tiempo']);
        // El display sale del término, no del código: la UI muestra el código si falta.
        $this->assertNull($fields['termino']);
    }

    public function testToothStateFieldsDiscardsUnknownTimeQualifier(): void
    {
        $fields = OdontologyEncounterService::toothStateFields(['Codigo' => 'corona', 'Tiempo' => 'alguna vez']);

        $this->assertNotNull($fields);
        $this->assertNull($fields['tiempo']);
    }

    public function testDentitionFollowsFdiNotation(): void
    {
        $this->assertSame(
            OdontologiaItemRowContract::DENTITION_PERMANENT,
            OdontologiaItemRowContract::dentitionForTooth('16')
        );
        $this->assertSame(
            OdontologiaItemRowContract::DENTITION_PERMANENT,
            OdontologiaItemRowContract::dentitionForTooth('48')
        );
        $this->assertSame(
            OdontologiaItemRowContract::DENTITION_TEMPORARY,
            OdontologiaItemRowContract::dentitionForTooth('51')
        );
        $this->assertSame(
            OdontologiaItemRowContract::DENTITION_TEMPORARY,
            OdontologiaItemRowContract::dentitionForTooth('85')
        );

        foreach (['', '49', '50', '86', 'derecha', null] as $invalid) {
            $this->assertNull(OdontologiaItemRowContract::dentitionForTooth($invalid));
        }
    }

    public function testBuildOdontogramGroupsByToothAndKeepsHistory(): void
    {
        $states = [
            ['pieza' => '16', 'codigo' => '33070004', 'caras' => 'O', 'tipo' => 'Carie', 'tiempo' => 'PASADA'],
            ['pieza' => '11', 'codigo' => 'ausente', 'caras' => null, 'tipo' => null, 'tiempo' => null],
            ['pieza' => '16', 'codigo' => 'obturada', 'caras' => 'O', 'tipo' => 'Estado', 'tiempo' => 'PRESENTE'],
            ['pieza' => null, 'codigo' => '543', 'caras' => null, 'tipo' => null, 'tiempo' => null],
        ];

        $chart = OdontologyEncounterService::buildOdontogram($states, 42);

        $this->assertSame(42, $chart['encounterId']);
        $this->assertSame(['11', '16'], array_column($chart['teeth'], 'tooth'));
        $this->assertSame(
            [OdontologiaItemRowContract::DENTITION_PERMANENT, OdontologiaItemRowContract::DENTITION_PERMANENT],
            array_column($chart['teeth'], 'dentition')
        );

        $first = $chart['teeth'][0];
        $this->assertSame('ausente', $first['current']['codigo']);
        $this->assertCount(1, $first['history']);

        $second = $chart['teeth'][1];
        $this->assertSame('obturada', $second['current']['codigo']);
        $this->assertCount(2, $second['history']);

        $this->assertSame(
            ['teeth' => 2, 'states' => 4, 'unassigned' => 1],
            $chart['totals']
        );
    }

    public function testBuildOdontogramWithoutStatesIsEmpty(): void
    {
        $chart = OdontologyEncounterService::buildOdontogram([], 7);

        $this->assertSame([], $chart['teeth']);
        $this->assertSame(['teeth' => 0, 'states' => 0, 'unassigned' => 0], $chart['totals']);
    }
}
