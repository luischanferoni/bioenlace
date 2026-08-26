<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Service\Seed\ProvinciasArgentinaSeedService;

class ProvinciasArgentinaSeedServiceTest extends Unit
{
    public function testCanonicalDeclaraVeinticuatroProvincias(): void
    {
        $rows = ProvinciasArgentinaSeedService::canonicalRows();
        $this->assertCount(ProvinciasArgentinaSeedService::EXPECTED_COUNT, $rows);
    }

    public function testCodigosIndecUnicos(): void
    {
        $codigos = [];
        foreach (ProvinciasArgentinaSeedService::canonicalRows() as $row) {
            $cod = $row['cod_indec'];
            $this->assertNotContains($cod, $codigos, 'cod_indec duplicado: ' . $cod);
            $codigos[] = $cod;
        }
        $this->assertCount(ProvinciasArgentinaSeedService::EXPECTED_COUNT, $codigos);
    }

    public function testCodigosIndecOficialesSantaFeYSantiago(): void
    {
        $byCod = [];
        foreach (ProvinciasArgentinaSeedService::canonicalRows() as $row) {
            $byCod[$row['cod_indec']] = $row['nombre'];
        }
        $this->assertSame('Santa Fe', $byCod['82']);
        $this->assertSame('Santiago del Estero', $byCod['86']);
    }
}
