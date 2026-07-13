<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Service\Seed\ProvinciasArgentinaSeedService;
use Symfony\Component\Yaml\Yaml;
use Yii;

class ProvinciasArgentinaSeedServiceTest extends Unit
{
    public function testYamlDeclaraVeinticuatroProvincias(): void
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/provincias-argentina.yaml');
        $parsed = Yaml::parseFile($path);
        $this->assertIsArray($parsed);
        $provincias = $parsed['provincias'] ?? [];
        $this->assertCount(ProvinciasArgentinaSeedService::EXPECTED_COUNT, $provincias);
    }

    public function testCodigosIndecUnicosEnYaml(): void
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/provincias-argentina.yaml');
        $parsed = Yaml::parseFile($path);
        $codigos = [];
        foreach ($parsed['provincias'] as $row) {
            $cod = str_pad(trim((string) ($row['cod_indec'] ?? '')), 2, '0', STR_PAD_LEFT);
            $this->assertNotContains($cod, $codigos, 'cod_indec duplicado: ' . $cod);
            $codigos[] = $cod;
        }
        $this->assertCount(ProvinciasArgentinaSeedService::EXPECTED_COUNT, $codigos);
    }

    public function testCodigosIndecOficialesSantaFeYSantiago(): void
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/provincias-argentina.yaml');
        $parsed = Yaml::parseFile($path);
        $byCod = [];
        foreach ($parsed['provincias'] as $row) {
            $byCod[str_pad(trim((string) $row['cod_indec']), 2, '0', STR_PAD_LEFT)] = (string) $row['nombre'];
        }
        $this->assertSame('Santa Fe', $byCod['82']);
        $this->assertSame('Santiago del Estero', $byCod['86']);
    }
}
