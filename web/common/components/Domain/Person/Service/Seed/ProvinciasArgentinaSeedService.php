<?php

namespace common\components\Domain\Person\Service\Seed;

use common\models\Provincia;
use Symfony\Component\Yaml\Yaml;
use Yii;
use yii\db\Query;

/**
 * Carga idempotente las 24 jurisdicciones argentinas en {{%provincias}}.
 *
 * Fuente: @common/metadata/bioenlace/geo/provincias-argentina.yaml
 */
final class ProvinciasArgentinaSeedService
{
    public const EXPECTED_COUNT = 24;

    /**
     * @return array{inserted: int, updated: int, total: int, codigos: list<string>}
     */
    public function upsertAll(): array
    {
        $rows = $this->loadDefinition();
        $inserted = 0;
        $updated = 0;
        $codigos = [];

        foreach ($rows as $row) {
            $codIndec = $row['cod_indec'];
            $codigos[] = $codIndec;
            $existing = Provincia::findOne(['cod_indec' => $codIndec]);
            if ($existing === null && $codIndec === '02') {
                $legacy = Provincia::findOne(['cod_indec' => '00']);
                if ($legacy instanceof Provincia) {
                    $existing = $legacy;
                }
            }

            if ($existing instanceof Provincia) {
                $existing->nombre = $row['nombre'];
                $existing->region_pais = $row['region_pais'];
                $existing->superficie = $row['superficie'];
                if ($existing->cod_indec === '00' && $codIndec === '02') {
                    $existing->cod_indec = '02';
                }
                if (!$existing->save()) {
                    throw new \RuntimeException(
                        'No se pudo actualizar provincia ' . $codIndec . ': ' . json_encode($existing->getErrors())
                    );
                }
                $updated++;

                continue;
            }

            $provincia = new Provincia();
            $provincia->id_provincia = $this->resolveIdProvincia($codIndec);
            $provincia->cod_indec = $codIndec;
            $provincia->nombre = $row['nombre'];
            $provincia->region_pais = $row['region_pais'];
            $provincia->superficie = $row['superficie'];
            if (!$provincia->save()) {
                throw new \RuntimeException(
                    'No se pudo insertar provincia ' . $codIndec . ': ' . json_encode($provincia->getErrors())
                );
            }
            $inserted++;
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => count($rows),
            'codigos' => $codigos,
        ];
    }

    /**
     * @return list<array{cod_indec: string, nombre: string, region_pais: string, superficie: int}>
     */
    private function loadDefinition(): array
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/provincias-argentina.yaml');
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró provincias-argentina.yaml');
        }
        $parsed = Yaml::parseFile($path);
        if (!is_array($parsed)) {
            throw new \RuntimeException('provincias-argentina.yaml inválido');
        }
        $provincias = $parsed['provincias'] ?? [];
        if (!is_array($provincias) || count($provincias) < self::EXPECTED_COUNT) {
            throw new \RuntimeException('provincias-argentina.yaml debe declarar al menos ' . self::EXPECTED_COUNT . ' provincias.');
        }

        $out = [];
        foreach ($provincias as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cod = str_pad(trim((string) ($row['cod_indec'] ?? '')), 2, '0', STR_PAD_LEFT);
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $region = trim((string) ($row['region_pais'] ?? ''));
            $superficie = (int) ($row['superficie'] ?? 0);
            if ($cod === '' || $nombre === '' || $region === '' || $superficie <= 0) {
                throw new \RuntimeException('Provincia incompleta en YAML (cod_indec=' . $cod . ').');
            }
            if (mb_strlen($nombre) > 20 || mb_strlen($region) > 20) {
                throw new \RuntimeException('Nombre o región excede 20 caracteres (cod_indec=' . $cod . ').');
            }
            $out[] = [
                'cod_indec' => $cod,
                'nombre' => $nombre,
                'region_pais' => $region,
                'superficie' => $superficie,
            ];
        }

        return $out;
    }

    private function resolveIdProvincia(string $codIndec): int
    {
        $preferred = (int) $codIndec;
        if ($preferred > 0) {
            $byId = Provincia::findOne($preferred);
            if ($byId === null) {
                return $preferred;
            }
            if ((string) $byId->cod_indec === $codIndec) {
                return $preferred;
            }
        }

        $max = (new Query())
            ->from('{{%provincias}}')
            ->max('id_provincia', Yii::$app->db);

        return max(1, (int) $max + 1);
    }
}
