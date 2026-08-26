<?php

namespace common\components\Domain\Person\Service\Seed;

use common\models\Pais;
use common\models\Provincia;
use Yii;
use yii\db\Query;

/**
 * Seed console: 24 jurisdicciones AR en geo_provincias (+ id_pais).
 */
final class ProvinciasArgentinaSeedService
{
    public const EXPECTED_COUNT = 24;

    /**
     * @return list<array{cod_indec: string, nombre: string, region_pais: string, superficie: int}>
     */
    public static function canonicalRows(): array
    {
        return [
            ['cod_indec' => '02', 'nombre' => 'CABA', 'region_pais' => 'Pampeana', 'superficie' => 203],
            ['cod_indec' => '06', 'nombre' => 'Buenos Aires', 'region_pais' => 'Pampeana', 'superficie' => 307571],
            ['cod_indec' => '10', 'nombre' => 'Catamarca', 'region_pais' => 'Norte', 'superficie' => 102602],
            ['cod_indec' => '14', 'nombre' => 'Córdoba', 'region_pais' => 'Centro', 'superficie' => 165321],
            ['cod_indec' => '18', 'nombre' => 'Corrientes', 'region_pais' => 'Litoral', 'superficie' => 88199],
            ['cod_indec' => '22', 'nombre' => 'Chaco', 'region_pais' => 'Litoral', 'superficie' => 99633],
            ['cod_indec' => '26', 'nombre' => 'Chubut', 'region_pais' => 'Patagonia', 'superficie' => 224686],
            ['cod_indec' => '30', 'nombre' => 'Entre Ríos', 'region_pais' => 'Litoral', 'superficie' => 78781],
            ['cod_indec' => '34', 'nombre' => 'Formosa', 'region_pais' => 'Litoral', 'superficie' => 72906],
            ['cod_indec' => '38', 'nombre' => 'Jujuy', 'region_pais' => 'Norte', 'superficie' => 53219],
            ['cod_indec' => '42', 'nombre' => 'La Pampa', 'region_pais' => 'Pampeana', 'superficie' => 143440],
            ['cod_indec' => '46', 'nombre' => 'La Rioja', 'region_pais' => 'Cuyo', 'superficie' => 89680],
            ['cod_indec' => '50', 'nombre' => 'Mendoza', 'region_pais' => 'Cuyo', 'superficie' => 148827],
            ['cod_indec' => '54', 'nombre' => 'Misiones', 'region_pais' => 'Litoral', 'superficie' => 29801],
            ['cod_indec' => '58', 'nombre' => 'Neuquén', 'region_pais' => 'Patagonia', 'superficie' => 94078],
            ['cod_indec' => '62', 'nombre' => 'Río Negro', 'region_pais' => 'Patagonia', 'superficie' => 203013],
            ['cod_indec' => '66', 'nombre' => 'Salta', 'region_pais' => 'Norte', 'superficie' => 155488],
            ['cod_indec' => '70', 'nombre' => 'San Juan', 'region_pais' => 'Cuyo', 'superficie' => 89651],
            ['cod_indec' => '74', 'nombre' => 'San Luis', 'region_pais' => 'Cuyo', 'superficie' => 76748],
            ['cod_indec' => '78', 'nombre' => 'Santa Cruz', 'region_pais' => 'Patagonia', 'superficie' => 243943],
            ['cod_indec' => '82', 'nombre' => 'Santa Fe', 'region_pais' => 'Centro', 'superficie' => 133007],
            ['cod_indec' => '86', 'nombre' => 'Santiago del Estero', 'region_pais' => 'Norte', 'superficie' => 136351],
            ['cod_indec' => '90', 'nombre' => 'Tucumán', 'region_pais' => 'Norte', 'superficie' => 22524],
            ['cod_indec' => '94', 'nombre' => 'Tierra del Fuego', 'region_pais' => 'Patagonia', 'superficie' => 21185],
        ];
    }

    /**
     * @return array{inserted: int, updated: int, total: int, codigos: list<string>}
     */
    public function upsertAll(): array
    {
        $idPais = (int) Pais::requireByIso2('AR')->id_pais;
        $rows = self::canonicalRows();
        $this->realignCodIndecByCanonicalNombre($rows, $idPais);
        $inserted = 0;
        $updated = 0;
        $codigos = [];

        foreach ($rows as $row) {
            $codIndec = $row['cod_indec'];
            $codigos[] = $codIndec;
            $existing = Provincia::findOne(['id_pais' => $idPais, 'cod_indec' => $codIndec]);
            if ($existing === null && $codIndec === '02') {
                $legacy = Provincia::findOne(['id_pais' => $idPais, 'cod_indec' => '00']);
                if ($legacy instanceof Provincia) {
                    $existing = $legacy;
                }
            }
            if ($existing === null) {
                $existing = $this->findByCanonicalNombre($row['nombre'], $idPais);
            }

            if ($existing instanceof Provincia) {
                $existing->id_pais = $idPais;
                $existing->nombre = $row['nombre'];
                $existing->region_pais = $row['region_pais'];
                $existing->superficie = $row['superficie'];
                if ($existing->cod_indec === '00' && $codIndec === '02') {
                    $existing->cod_indec = '02';
                } elseif ((string) $existing->cod_indec !== $codIndec) {
                    $existing->cod_indec = $codIndec;
                }
                if (!$existing->save()) {
                    throw new \RuntimeException(
                        'No se pudo actualizar provincia AR ' . $codIndec . ': ' . json_encode($existing->getErrors())
                    );
                }
                $updated++;

                continue;
            }

            $provincia = new Provincia();
            $provincia->id_provincia = $this->resolveIdProvincia($codIndec, $idPais);
            $provincia->id_pais = $idPais;
            $provincia->cod_indec = $codIndec;
            $provincia->nombre = $row['nombre'];
            $provincia->region_pais = $row['region_pais'];
            $provincia->superficie = $row['superficie'];
            if (!$provincia->save()) {
                throw new \RuntimeException(
                    'No se pudo insertar provincia AR ' . $codIndec . ': ' . json_encode($provincia->getErrors())
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

    private function resolveIdProvincia(string $codIndec, int $idPais): int
    {
        $preferred = (int) $codIndec;
        if ($preferred > 0) {
            $byId = Provincia::findOne($preferred);
            if ($byId === null) {
                return $preferred;
            }
            if ((string) $byId->cod_indec === $codIndec && (int) $byId->id_pais === $idPais) {
                return $preferred;
            }
        }

        $max = (new Query())
            ->from('{{%geo_provincias}}')
            ->max('id_provincia', Yii::$app->db);

        return max(1, (int) $max + 1);
    }

    /**
     * @param list<array{cod_indec: string, nombre: string, region_pais: string, superficie: int}> $rows
     */
    private function realignCodIndecByCanonicalNombre(array $rows, int $idPais): void
    {
        $byNombre = [];
        foreach ($rows as $row) {
            $byNombre[$this->normalizeNombre($row['nombre'])] = $row['cod_indec'];
        }

        $pending = [];
        foreach (Provincia::find()->where(['id_pais' => $idPais])->all() as $provincia) {
            $key = $this->normalizeNombre((string) $provincia->nombre);
            if (!isset($byNombre[$key])) {
                continue;
            }
            $target = $byNombre[$key];
            if ((string) $provincia->cod_indec === $target) {
                continue;
            }
            $pending[] = [$provincia, $target];
        }

        if ($pending === []) {
            return;
        }

        foreach ($pending as $i => [$provincia]) {
            $provincia->cod_indec = chr(ord('a') + intdiv($i, 10)) . (string) ($i % 10);
            if (!$provincia->save(false, ['cod_indec'])) {
                throw new \RuntimeException(
                    'No se pudo liberar cod_indec temporal de provincia ' . $provincia->id_provincia
                );
            }
        }

        foreach ($pending as [$provincia, $target]) {
            $provincia->cod_indec = $target;
            if (!$provincia->save(false, ['cod_indec'])) {
                throw new \RuntimeException(
                    'No se pudo realinear cod_indec=' . $target . ' en provincia ' . $provincia->id_provincia
                );
            }
        }
    }

    private function findByCanonicalNombre(string $nombre, int $idPais): ?Provincia
    {
        $target = $this->normalizeNombre($nombre);
        foreach (Provincia::find()->where(['id_pais' => $idPais])->all() as $provincia) {
            if ($this->normalizeNombre((string) $provincia->nombre) === $target) {
                return $provincia;
            }
        }

        return null;
    }

    private function normalizeNombre(string $nombre): string
    {
        $nombre = mb_strtolower(trim($nombre), 'UTF-8');
        $repl = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'];

        return strtr($nombre, $repl);
    }
}
