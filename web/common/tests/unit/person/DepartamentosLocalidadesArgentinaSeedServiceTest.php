<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Service\Seed\DepartamentosLocalidadesArgentinaSeedService;
use Yii;

class DepartamentosLocalidadesArgentinaSeedServiceTest extends Unit
{
    public function testGzipDeclaraCatalogoCompleto(): void
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/departamentos-localidades-argentina.json.gz');
        $this->assertFileExists($path);
        $raw = gzdecode((string) file_get_contents($path));
        $this->assertNotFalse($raw);
        $parsed = json_decode($raw, true);
        $this->assertIsArray($parsed);
        $this->assertCount(
            DepartamentosLocalidadesArgentinaSeedService::EXPECTED_DEPARTAMENTOS,
            $parsed['departamentos']
        );
        $this->assertCount(
            DepartamentosLocalidadesArgentinaSeedService::EXPECTED_LOCALIDADES,
            $parsed['localidades']
        );
    }

    public function testCapitalesDeclaradasEnSeed(): void
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/departamentos-localidades-argentina.json.gz');
        $parsed = json_decode(gzdecode((string) file_get_contents($path)), true);
        $byBahra = [];
        foreach ($parsed['localidades'] as $row) {
            $byBahra[(string) $row['cod_bahra']] = $row;
        }
        $this->assertArrayHasKey(
            DepartamentosLocalidadesArgentinaSeedService::COD_BAHRA_SANTIAGO_DEL_ESTERO,
            $byBahra
        );
        $this->assertArrayHasKey(
            DepartamentosLocalidadesArgentinaSeedService::COD_BAHRA_SANTA_FE,
            $byBahra
        );
        $this->assertContains(
            'Santiago',
            (string) $byBahra[DepartamentosLocalidadesArgentinaSeedService::COD_BAHRA_SANTIAGO_DEL_ESTERO]['nombre']
        );
        $this->assertContains(
            'Santa Fe',
            (string) $byBahra[DepartamentosLocalidadesArgentinaSeedService::COD_BAHRA_SANTA_FE]['nombre']
        );
    }

    public function testProvinciasYamlAlineadaConIndecGeoref(): void
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/provincias-argentina.yaml');
        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($path);
        $byCod = [];
        foreach ($parsed['provincias'] as $row) {
            $byCod[str_pad((string) $row['cod_indec'], 2, '0', STR_PAD_LEFT)] = (string) $row['nombre'];
        }
        $this->assertSame('Santa Fe', $byCod['82']);
        $this->assertSame('Santiago del Estero', $byCod['86']);
    }
}
