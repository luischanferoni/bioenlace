<?php

namespace common\components\Domain\Person\Service\Seed;

use common\models\Pais;
use common\models\Provincia;
use Yii;
use yii\db\Query;

/**
 * Seed console de prueba: 19 departamentos de Uruguay.
 */
final class ProvinciasUruguaySeedService
{
    public const EXPECTED_COUNT = 19;

    /**
     * @return list<array{cod_indec: string, nombre: string, region_pais: string, superficie: int}>
     */
    public static function canonicalRows(): array
    {
        return [
            ['cod_indec' => 'AR', 'nombre' => 'Artigas', 'region_pais' => 'Norte', 'superficie' => 11928],
            ['cod_indec' => 'CA', 'nombre' => 'Canelones', 'region_pais' => 'Sur', 'superficie' => 4536],
            ['cod_indec' => 'CL', 'nombre' => 'Cerro Largo', 'region_pais' => 'Este', 'superficie' => 13648],
            ['cod_indec' => 'CO', 'nombre' => 'Colonia', 'region_pais' => 'Sur', 'superficie' => 6106],
            ['cod_indec' => 'DU', 'nombre' => 'Durazno', 'region_pais' => 'Centro', 'superficie' => 11643],
            ['cod_indec' => 'FS', 'nombre' => 'Flores', 'region_pais' => 'Centro', 'superficie' => 5144],
            ['cod_indec' => 'FD', 'nombre' => 'Florida', 'region_pais' => 'Centro', 'superficie' => 10417],
            ['cod_indec' => 'LA', 'nombre' => 'Lavalleja', 'region_pais' => 'Este', 'superficie' => 10016],
            ['cod_indec' => 'MA', 'nombre' => 'Maldonado', 'region_pais' => 'Este', 'superficie' => 4793],
            ['cod_indec' => 'MO', 'nombre' => 'Montevideo', 'region_pais' => 'Sur', 'superficie' => 530],
            ['cod_indec' => 'PA', 'nombre' => 'Paysandú', 'region_pais' => 'Litoral', 'superficie' => 13922],
            ['cod_indec' => 'RN', 'nombre' => 'Río Negro', 'region_pais' => 'Litoral', 'superficie' => 9282],
            ['cod_indec' => 'RV', 'nombre' => 'Rivera', 'region_pais' => 'Norte', 'superficie' => 9370],
            ['cod_indec' => 'RO', 'nombre' => 'Rocha', 'region_pais' => 'Este', 'superficie' => 10551],
            ['cod_indec' => 'SA', 'nombre' => 'Salto', 'region_pais' => 'Litoral', 'superficie' => 14163],
            ['cod_indec' => 'SJ', 'nombre' => 'San José', 'region_pais' => 'Sur', 'superficie' => 4992],
            ['cod_indec' => 'SO', 'nombre' => 'Soriano', 'region_pais' => 'Litoral', 'superficie' => 9008],
            ['cod_indec' => 'TA', 'nombre' => 'Tacuarembó', 'region_pais' => 'Norte', 'superficie' => 15438],
            ['cod_indec' => 'TT', 'nombre' => 'Treinta y Tres', 'region_pais' => 'Este', 'superficie' => 9529],
        ];
    }

    /**
     * @return array{inserted: int, updated: int, total: int, codigos: list<string>}
     */
    public function upsertAll(): array
    {
        $idPais = Pais::ID_URUGUAY;
        $rows = self::canonicalRows();
        $inserted = 0;
        $updated = 0;
        $codigos = [];

        foreach ($rows as $row) {
            $cod = $row['cod_indec'];
            $codigos[] = $cod;
            $existing = Provincia::findOne(['id_pais' => $idPais, 'cod_indec' => $cod]);
            if ($existing instanceof Provincia) {
                $existing->nombre = $row['nombre'];
                $existing->region_pais = $row['region_pais'];
                $existing->superficie = $row['superficie'];
                if (!$existing->save()) {
                    throw new \RuntimeException(
                        'No se pudo actualizar provincia UY ' . $cod . ': ' . json_encode($existing->getErrors())
                    );
                }
                $updated++;

                continue;
            }

            $provincia = new Provincia();
            $provincia->id_provincia = $this->nextId();
            $provincia->id_pais = $idPais;
            $provincia->cod_indec = $cod;
            $provincia->nombre = $row['nombre'];
            $provincia->region_pais = $row['region_pais'];
            $provincia->superficie = $row['superficie'];
            if (!$provincia->save()) {
                throw new \RuntimeException(
                    'No se pudo insertar provincia UY ' . $cod . ': ' . json_encode($provincia->getErrors())
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

    private function nextId(): int
    {
        $max = (new Query())
            ->from('{{%geo_provincias}}')
            ->max('id_provincia', Yii::$app->db);

        return max(200, (int) $max + 1);
    }
}
