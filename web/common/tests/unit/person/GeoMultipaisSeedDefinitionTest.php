<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Service\Seed\ProvinciasArgentinaSeedService;
use common\components\Domain\Person\Service\Seed\ProvinciasUruguaySeedService;
use common\components\Domain\Person\Service\Seed\ProvinciaVecinosSeedService;

class GeoMultipaisSeedDefinitionTest extends Unit
{
    public function testArgentinaCanonicalCountAndCodes(): void
    {
        $rows = ProvinciasArgentinaSeedService::canonicalRows();
        $this->assertCount(ProvinciasArgentinaSeedService::EXPECTED_COUNT, $rows);
        $byCod = [];
        foreach ($rows as $row) {
            $byCod[$row['cod_indec']] = $row['nombre'];
        }
        $this->assertSame('Santa Fe', $byCod['82']);
        $this->assertSame('Santiago del Estero', $byCod['86']);
    }

    public function testUruguayCanonicalCount(): void
    {
        $rows = ProvinciasUruguaySeedService::canonicalRows();
        $this->assertCount(ProvinciasUruguaySeedService::EXPECTED_COUNT, $rows);
        $codigos = array_column($rows, 'cod_indec');
        $this->assertContains('MO', $codigos);
        $this->assertCount(count($codigos), array_unique($codigos));
    }

    public function testVecinosMapsCoverCanonicalCodes(): void
    {
        $ar = array_column(ProvinciasArgentinaSeedService::canonicalRows(), 'cod_indec');
        foreach (ProvinciaVecinosSeedService::vecinosArgentinaPorCod() as $cod => $vecinos) {
            $this->assertContains($cod, $ar, 'vecino AR origen desconocido: ' . $cod);
            foreach ($vecinos as $v) {
                $this->assertContains($v, $ar, 'vecino AR destino desconocido: ' . $v);
            }
        }
        $uy = array_column(ProvinciasUruguaySeedService::canonicalRows(), 'cod_indec');
        foreach (ProvinciaVecinosSeedService::vecinosUruguayPorCod() as $cod => $vecinos) {
            $this->assertContains($cod, $uy, 'vecino UY origen desconocido: ' . $cod);
            foreach ($vecinos as $v) {
                $this->assertContains($v, $uy, 'vecino UY destino desconocido: ' . $v);
            }
        }
    }
}
