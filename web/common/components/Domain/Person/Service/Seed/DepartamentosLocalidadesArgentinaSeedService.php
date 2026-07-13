<?php

namespace common\components\Domain\Person\Service\Seed;

use common\models\Provincia;
use Yii;
use yii\db\Query;

/**
 * Seed idempotente de departamentos y localidades (Argentina, Georef/INDEC).
 *
 * Fuente: @common/metadata/bioenlace/geo/departamentos-localidades-argentina.json.gz
 */
final class DepartamentosLocalidadesArgentinaSeedService
{
    public const EXPECTED_DEPARTAMENTOS = 529;

    public const EXPECTED_LOCALIDADES = 4037;

    /** Localidad cabecera SDE (Capital) — Georef/BAHRA. */
    public const COD_BAHRA_SANTIAGO_DEL_ESTERO = '86049110';

    /** Localidad cabecera Santa Fe (La Capital) — Georef/BAHRA. */
    public const COD_BAHRA_SANTA_FE = '82063170';

    /** Preferencia de PK legacy en Bioenlace (Santiago del Estero). */
    public const PREFERRED_ID_PROVINCIA_SDE = 1;

    /** Preferencia de PK legacy en Bioenlace (Santa Fe). */
    public const PREFERRED_ID_PROVINCIA_SF = 86;

    public const EFECTOR_SANTA_FE_DEMO_ID = 1509;

    public const COD_INDEC_SANTIAGO = '86';

    public const COD_INDEC_SANTA_FE = '82';

    /**
     * @return array{
     *     departamentos_inserted: int,
     *     departamentos_updated: int,
     *     localidades_inserted: int,
     *     localidades_updated: int,
     *     total_departamentos: int,
     *     total_localidades: int
     * }
     */
    public function upsertAll(): array
    {
        $payload = $this->loadDefinition();
        $provinciaIdByCod = $this->mapProvinciaIdByCodIndec();

        $deptInserted = 0;
        $deptUpdated = 0;
        $deptIdByCod = [];

        foreach ($payload['departamentos'] as $row) {
            $codIndec = (string) $row['cod_indec'];
            $codProvincia = (string) $row['cod_provincia'];
            if (!isset($provinciaIdByCod[$codProvincia])) {
                throw new \RuntimeException(
                    'Falta provincia cod_indec=' . $codProvincia . ' para departamento ' . $codIndec
                );
            }
            $idProvincia = $provinciaIdByCod[$codProvincia];
            $idDepartamento = (int) $codIndec;
            $nombre = mb_substr(trim((string) $row['nombre']), 0, 40, 'UTF-8');

            $existingId = (new Query())
                ->from('{{%geo_departamentos}}')
                ->select('id_departamento')
                ->where(['cod_indec' => $codIndec])
                ->scalar();

            if ($existingId === false) {
                $byPk = (new Query())
                    ->from('{{%geo_departamentos}}')
                    ->where(['id_departamento' => $idDepartamento])
                    ->exists();
                if ($byPk) {
                    Yii::$app->db->createCommand()->update('{{%geo_departamentos}}', [
                        'nombre' => $nombre,
                        'cod_indec' => $codIndec,
                        'id_provincia' => $idProvincia,
                    ], ['id_departamento' => $idDepartamento])->execute();
                    $deptUpdated++;
                    $deptIdByCod[$codIndec] = $idDepartamento;
                } else {
                    Yii::$app->db->createCommand()->insert('{{%geo_departamentos}}', [
                        'id_departamento' => $idDepartamento,
                        'nombre' => $nombre,
                        'cod_indec' => $codIndec,
                        'id_provincia' => $idProvincia,
                    ])->execute();
                    $deptInserted++;
                    $deptIdByCod[$codIndec] = $idDepartamento;
                }
            } else {
                $idDepartamento = (int) $existingId;
                Yii::$app->db->createCommand()->update('{{%geo_departamentos}}', [
                    'nombre' => $nombre,
                    'id_provincia' => $idProvincia,
                ], ['id_departamento' => $idDepartamento])->execute();
                $deptUpdated++;
                $deptIdByCod[$codIndec] = $idDepartamento;
            }
        }

        // Recargar mapa por si había departamentos previos solo por PK.
        foreach ((new Query())->from('{{%geo_departamentos}}')->select(['id_departamento', 'cod_indec'])->all() as $d) {
            $deptIdByCod[(string) $d['cod_indec']] = (int) $d['id_departamento'];
        }

        $locInserted = 0;
        $locUpdated = 0;
        $nextId = (int) (new Query())->from('{{%geo_localidades}}')->max('id_localidad') + 1;
        if ($nextId < 1) {
            $nextId = 1;
        }
        $usedPostal = [];
        foreach ((new Query())->from('{{%geo_localidades}}')->select('cod_postal')->column() as $cp) {
            $usedPostal[(string) $cp] = true;
        }
        $postalSeq = 1;

        $hasCodBahra = $this->localidadesHasColumn('cod_bahra');
        $hasCodSisa = $this->localidadesHasColumn('cod_sisa');

        foreach ($payload['localidades'] as $row) {
            $codBahra = trim((string) $row['cod_bahra']);
            $codDepartamento = (string) $row['cod_departamento'];
            if ($codBahra === '' || !isset($deptIdByCod[$codDepartamento])) {
                throw new \RuntimeException(
                    'Localidad sin departamento (' . $codDepartamento . ') o sin cod_bahra'
                );
            }
            $idDepartamento = $deptIdByCod[$codDepartamento];
            $nombre = mb_substr(trim((string) $row['nombre']), 0, 100, 'UTF-8');
            $codPostalPreferido = preg_replace('/\D+/', '', (string) ($row['cod_postal'] ?? '')) ?: '';
            $codPostalPreferido = substr($codPostalPreferido, 0, 5);

            $existing = null;
            if ($hasCodBahra) {
                $existing = (new Query())
                    ->from('{{%geo_localidades}}')
                    ->where(['cod_bahra' => $codBahra])
                    ->one();
            }

            if (is_array($existing)) {
                $update = [
                    'nombre' => $nombre,
                    'id_departamento' => $idDepartamento,
                ];
                if (!$hasCodBahra || (string) ($existing['cod_postal'] ?? '') === '') {
                    // keep postal
                }
                Yii::$app->db->createCommand()->update(
                    '{{%geo_localidades}}',
                    $update,
                    ['id_localidad' => (int) $existing['id_localidad']]
                )->execute();
                $locUpdated++;
                continue;
            }

            $codPostal = $codPostalPreferido;
            if ($codPostal === '' || isset($usedPostal[$codPostal])) {
                do {
                    $codPostal = str_pad((string) ($postalSeq % 100000), 5, '0', STR_PAD_LEFT);
                    $postalSeq++;
                } while (isset($usedPostal[$codPostal]));
            }
            $usedPostal[$codPostal] = true;

            $insert = [
                'id_localidad' => $nextId,
                'nombre' => $nombre,
                'cod_postal' => $codPostal,
                'id_departamento' => $idDepartamento,
            ];
            if ($hasCodBahra) {
                $insert['cod_bahra'] = $codBahra;
            }
            if ($hasCodSisa) {
                $insert['cod_sisa'] = substr($codBahra, 0, 15);
            }

            Yii::$app->db->createCommand()->insert('{{%geo_localidades}}', $insert)->execute();
            $nextId++;
            $locInserted++;
        }

        return [
            'departamentos_inserted' => $deptInserted,
            'departamentos_updated' => $deptUpdated,
            'localidades_inserted' => $locInserted,
            'localidades_updated' => $locUpdated,
            'total_departamentos' => count($payload['departamentos']),
            'total_localidades' => count($payload['localidades']),
        ];
    }

    /**
     * Asigna localidad cabecera de SDE a todos los efectores, excepto el demo Santa Fe.
     *
     * @return array{santiago_localidad: int, santa_fe_localidad: int, actualizados_sde: int, actualizados_sf: int}
     */
    public function reassignEfectoresToCapitales(): array
    {
        $idLocalidadSde = $this->requireLocalidadIdByCodBahra(self::COD_BAHRA_SANTIAGO_DEL_ESTERO);
        $idLocalidadSf = $this->requireLocalidadIdByCodBahra(self::COD_BAHRA_SANTA_FE);

        $this->assertLocalidadEnProvinciaPreferida(
            $idLocalidadSde,
            self::PREFERRED_ID_PROVINCIA_SDE,
            'Santiago del Estero',
            self::COD_INDEC_SANTIAGO
        );
        $this->assertLocalidadEnProvinciaPreferida(
            $idLocalidadSf,
            self::PREFERRED_ID_PROVINCIA_SF,
            'Santa Fe',
            self::COD_INDEC_SANTA_FE
        );

        $updatedSde = Yii::$app->db->createCommand()->update(
            '{{%efectores}}',
            ['id_localidad' => $idLocalidadSde],
            ['not', ['id_efector' => self::EFECTOR_SANTA_FE_DEMO_ID]]
        )->execute();

        $updatedSf = Yii::$app->db->createCommand()->update(
            '{{%efectores}}',
            ['id_localidad' => $idLocalidadSf],
            ['id_efector' => self::EFECTOR_SANTA_FE_DEMO_ID]
        )->execute();

        return [
            'santiago_localidad' => $idLocalidadSde,
            'santa_fe_localidad' => $idLocalidadSf,
            'actualizados_sde' => (int) $updatedSde,
            'actualizados_sf' => (int) $updatedSf,
        ];
    }

    /**
     * @return array{
     *     version: int|string|null,
     *     departamentos: list<array{cod_indec: string, nombre: string, cod_provincia: string}>,
     *     localidades: list<array{cod_bahra: string, nombre: string, cod_departamento: string, cod_postal?: string}>
     * }
     */
    private function loadDefinition(): array
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/departamentos-localidades-argentina.json.gz');
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró departamentos-localidades-argentina.json.gz');
        }
        $raw = @gzdecode((string) file_get_contents($path));
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('No se pudo descomprimir departamentos-localidades-argentina.json.gz');
        }
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('JSON geográfico inválido');
        }
        $departamentos = $parsed['departamentos'] ?? null;
        $localidades = $parsed['localidades'] ?? null;
        if (!is_array($departamentos) || count($departamentos) < self::EXPECTED_DEPARTAMENTOS) {
            throw new \RuntimeException('El seed debe declarar al menos ' . self::EXPECTED_DEPARTAMENTOS . ' departamentos.');
        }
        if (!is_array($localidades) || count($localidades) < self::EXPECTED_LOCALIDADES) {
            throw new \RuntimeException('El seed debe declarar al menos ' . self::EXPECTED_LOCALIDADES . ' localidades.');
        }

        return [
            'version' => $parsed['version'] ?? null,
            'departamentos' => $departamentos,
            'localidades' => $localidades,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function mapProvinciaIdByCodIndec(): array
    {
        $map = [];
        foreach ((new Query())->from('{{%geo_provincias}}')->select(['id_provincia', 'cod_indec'])->all() as $row) {
            $cod = str_pad(trim((string) $row['cod_indec']), 2, '0', STR_PAD_LEFT);
            $map[$cod] = (int) $row['id_provincia'];
        }
        if (count($map) < ProvinciasArgentinaSeedService::EXPECTED_COUNT) {
            throw new \RuntimeException(
                'Se esperaban al menos ' . ProvinciasArgentinaSeedService::EXPECTED_COUNT
                . ' provincias antes de sembrar departamentos/localidades.'
            );
        }

        return $map;
    }

    private function localidadesHasColumn(string $column): bool
    {
        $schema = Yii::$app->db->schema->getTableSchema('{{%geo_localidades}}', true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function requireLocalidadIdByCodBahra(string $codBahra): int
    {
        if (!$this->localidadesHasColumn('cod_bahra')) {
            throw new \RuntimeException('La tabla localidades no tiene columna cod_bahra.');
        }
        $id = (new Query())
            ->from('{{%geo_localidades}}')
            ->select('id_localidad')
            ->where(['cod_bahra' => $codBahra])
            ->scalar();
        if ($id === false || (int) $id <= 0) {
            throw new \RuntimeException('No se encontró localidad cod_bahra=' . $codBahra);
        }

        return (int) $id;
    }

    private function assertLocalidadEnProvinciaPreferida(
        int $idLocalidad,
        int $preferredIdProvincia,
        string $nombreProvincia,
        string $codIndec
    ): void {
        $idProvincia = (new Query())
            ->select('d.id_provincia')
            ->from(['l' => '{{%geo_localidades}}'])
            ->innerJoin(['d' => '{{%geo_departamentos}}'], 'd.id_departamento = l.id_departamento')
            ->where(['l.id_localidad' => $idLocalidad])
            ->scalar();

        if ($idProvincia === false) {
            throw new \RuntimeException('Localidad ' . $idLocalidad . ' sin departamento/provincia.');
        }
        $idProvincia = (int) $idProvincia;

        $preferred = Provincia::findOne($preferredIdProvincia);
        if ($preferred instanceof Provincia) {
            $normPreferred = mb_strtolower((string) $preferred->nombre, 'UTF-8');
            $normExpected = mb_strtolower($nombreProvincia, 'UTF-8');
            if (mb_strpos($normPreferred, $normExpected) !== false || mb_strpos($normExpected, $normPreferred) !== false) {
                if ($idProvincia !== $preferredIdProvincia) {
                    throw new \RuntimeException(sprintf(
                        'Localidad %d pertenece a id_provincia=%d; se esperaba %d (%s).',
                        $idLocalidad,
                        $idProvincia,
                        $preferredIdProvincia,
                        $nombreProvincia
                    ));
                }

                return;
            }
        }

        $byCod = Provincia::findOne(['cod_indec' => $codIndec]);
        if ($byCod instanceof Provincia && (int) $byCod->id_provincia === $idProvincia) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'No se pudo validar provincia de localidad %d (id_provincia=%d, preferida=%d, cod_indec=%s).',
            $idLocalidad,
            $idProvincia,
            $preferredIdProvincia,
            $codIndec
        ));
    }
}
