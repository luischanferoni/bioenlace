<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
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
}
