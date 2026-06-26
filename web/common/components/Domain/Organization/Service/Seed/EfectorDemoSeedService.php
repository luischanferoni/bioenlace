<?php

namespace common\components\Domain\Organization\Service\Seed;

use common\models\Departamento;
use common\models\Efector;
use common\models\Localidad;
use common\models\Provincia;
use yii\db\Query;

/**
 * Seed de desarrollo: efector público en otra provincia y clínica privada, cada uno con médico MED GENERAL.
 *
 * Idempotente por codigo_sisa reservado. Si el catálogo geográfico no tiene otra provincia,
 * crea provincia/departamento/localidad mínimos de demo (Santa Fe).
 */
final class EfectorDemoSeedService
{
    public const SEED_MARKER = 'seed:efector-demo-contexto';

    public const COD_SISA_PUBLIC_OTRA_PROV = 'DEV99001SFPUB';

    public const COD_SISA_PRIVATE = 'DEV99002PRIV';

    /** Efector de referencia (Santiago del Estero en prod). */
    public const DEFAULT_EFECTOR_REF = 863;

    private const DEV_LOCALIDAD_OTRA_PROV_COD_BAHRA = 'DEV99001SF';

    private const PREFER_PROVINCIA_NOMBRE = 'Santa Fe';

    private const PREFER_PROVINCIA_COD_INDEC = '82';

    /**
     * @return array{
     *     public: array<string, mixed>,
     *     private: array<string, mixed>
     * }
     */
    public function upsertAll(bool $withMedicos = true, bool $withAgenda = true): array
    {
        $medicoSeed = new MedicoMedGeneralEfectorSeedService();

        $public = $this->upsertPublicOtraProvincia();
        $private = $this->upsertClinicaPrivada();

        if ($withMedicos) {
            $public['medico'] = $medicoSeed->upsert((int) $public['id_efector'], $withAgenda);
            $private['medico'] = $medicoSeed->upsert((int) $private['id_efector'], $withAgenda);
        }

        return [
            'public' => $public,
            'private' => $private,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function upsertPublicOtraProvincia(int $idEfectorReferencia = self::DEFAULT_EFECTOR_REF): array
    {
        $localidad = $this->findLocalidadEnOtraProvincia($idEfectorReferencia);
        $provinciaNombre = $this->resolveProvinciaNombreForLocalidad($localidad);

        return $this->upsertEfector(
            self::COD_SISA_PUBLIC_OTRA_PROV,
            '[DEV] CAP ' . $provinciaNombre . ' Demo',
            'Provincial',
            'CAP',
            'Av. San Martín 100',
            'Provincial',
            '0342-4000000',
            (int) $localidad->id_localidad,
            $provinciaNombre
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function upsertClinicaPrivada(int $idEfectorReferencia = self::DEFAULT_EFECTOR_REF): array
    {
        $localidad = $this->findLocalidadDeEfector($idEfectorReferencia);
        $provinciaNombre = $this->resolveProvinciaNombreForLocalidad($localidad);

        return $this->upsertEfector(
            self::COD_SISA_PRIVATE,
            '[DEV] Clínica Privada Demo',
            'Privado',
            'CLIN',
            'Av. Independencia 250',
            'Privado',
            '0385-4220000',
            (int) $localidad->id_localidad,
            $provinciaNombre
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCodigoSisa(string $codigoSisa): ?array
    {
        $row = (new Query())
            ->from('{{%efectores}}')
            ->where(['codigo_sisa' => $codigoSisa])
            ->one();

        return $row !== false ? $row : null;
    }

    /**
     * @return array{removed_public: bool, removed_private: bool}
     */
    public function removeAll(bool $removeMedicos = true): array
    {
        $medicoSeed = new MedicoMedGeneralEfectorSeedService();
        $removedPublic = false;
        $removedPrivate = false;

        foreach ([self::COD_SISA_PUBLIC_OTRA_PROV, self::COD_SISA_PRIVATE] as $codigoSisa) {
            $efector = Efector::findOne(['codigo_sisa' => $codigoSisa]);
            if ($efector === null) {
                continue;
            }

            $idEfector = (int) $efector->id_efector;
            if ($removeMedicos) {
                $medicoSeed->remove($idEfector);
            }

            if ($codigoSisa === self::COD_SISA_PUBLIC_OTRA_PROV) {
                $removedPublic = (bool) $efector->delete();
            } else {
                $removedPrivate = (bool) $efector->delete();
            }
        }

        return [
            'removed_public' => $removedPublic,
            'removed_private' => $removedPrivate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function upsertEfector(
        string $codigoSisa,
        string $nombre,
        string $dependencia,
        string $tipologia,
        string $domicilio,
        string $origenFinanciamiento,
        string $telefono,
        int $idLocalidad,
        string $provinciaNombre
    ): array {
        $efector = Efector::findOne(['codigo_sisa' => $codigoSisa]);
        $created = $efector === null;
        if ($created) {
            $efector = new Efector();
            $efector->codigo_sisa = $codigoSisa;
        }

        $efector->nombre = $nombre;
        $efector->dependencia = $dependencia;
        $efector->tipologia = $tipologia;
        $efector->domicilio = $domicilio;
        $efector->origen_financiamiento = $origenFinanciamiento;
        $efector->id_localidad = $idLocalidad;
        $efector->telefono = $telefono;
        $efector->estado = 'ACTIVO';
        $efector->grupo = '0';
        $efector->implementado = 'F';

        if (!$efector->save()) {
            throw new \RuntimeException(
                'Efector seed ' . $codigoSisa . ': ' . json_encode($efector->getErrors())
            );
        }

        return [
            'created' => $created,
            'id_efector' => (int) $efector->id_efector,
            'codigo_sisa' => $codigoSisa,
            'nombre' => $nombre,
            'origen_financiamiento' => $origenFinanciamiento,
            'id_localidad' => $idLocalidad,
            'provincia' => $provinciaNombre,
        ];
    }

    private function findLocalidadEnOtraProvincia(int $idEfectorReferencia): Localidad
    {
        $homeProvinciaId = $this->resolveProvinciaIdFromEfector($idEfectorReferencia);

        $localidad = $this->queryPrimeraLocalidadEnProvinciaDistinta($homeProvinciaId);
        if ($localidad !== null) {
            return $localidad;
        }

        return $this->ensureLocalidadDemoOtraProvincia($homeProvinciaId);
    }

    private function findLocalidadDeEfector(int $idEfectorReferencia): Localidad
    {
        $efector = Efector::findOne($idEfectorReferencia);
        if ($efector === null) {
            $efector = Efector::find()
                ->where(['not', ['id_localidad' => null]])
                ->andWhere(['>', 'id_localidad', 0])
                ->orderBy(['id_efector' => SORT_ASC])
                ->one();
        }

        if ($efector === null || (int) $efector->id_localidad <= 0) {
            throw new \RuntimeException(
                'No hay efector de referencia con localidad (probá id_efector='
                . self::DEFAULT_EFECTOR_REF
                . ').'
            );
        }

        $localidad = Localidad::find()
            ->where(['id_localidad' => (int) $efector->id_localidad])
            ->with(['departamento.provincia'])
            ->one();

        if ($localidad === null) {
            throw new \RuntimeException(
                'No existe localidades.id_localidad=' . (int) $efector->id_localidad
                . ' para el efector ' . (int) $efector->id_efector . '.'
            );
        }

        return $localidad;
    }

    private function resolveProvinciaIdFromEfector(int $idEfector): ?int
    {
        $idLocalidad = (new Query())
            ->select('id_localidad')
            ->from('{{%efectores}}')
            ->where(['id_efector' => $idEfector])
            ->scalar();

        if ($idLocalidad === false || (int) $idLocalidad <= 0) {
            return null;
        }

        $idProvincia = (new Query())
            ->select('d.id_provincia')
            ->from(['l' => '{{%localidades}}'])
            ->innerJoin(['d' => '{{%departamentos}}'], 'd.id_departamento = l.id_departamento')
            ->where(['l.id_localidad' => (int) $idLocalidad])
            ->scalar();

        return $idProvincia !== false && (int) $idProvincia > 0 ? (int) $idProvincia : null;
    }

    private function queryPrimeraLocalidadEnProvinciaDistinta(?int $excludeProvinciaId): ?Localidad
    {
        $query = Localidad::find()
            ->alias('l')
            ->innerJoin(
                ['d' => Departamento::tableName()],
                'd.id_departamento = l.id_departamento'
            )
            ->with(['departamento.provincia'])
            ->orderBy(['l.id_localidad' => SORT_ASC]);

        if ($excludeProvinciaId !== null && $excludeProvinciaId > 0) {
            $query->andWhere(['not', ['d.id_provincia' => $excludeProvinciaId]]);
        }

        $localidad = $query->one();

        return $localidad instanceof Localidad ? $localidad : null;
    }

    private function ensureLocalidadDemoOtraProvincia(?int $homeProvinciaId): Localidad
    {
        $existing = Localidad::findOne(['cod_bahra' => self::DEV_LOCALIDAD_OTRA_PROV_COD_BAHRA]);
        if ($existing !== null) {
            return Localidad::find()
                ->where(['id_localidad' => (int) $existing->id_localidad])
                ->with(['departamento.provincia'])
                ->one() ?? $existing;
        }

        $provincia = $this->resolveProvinciaPreferidaOtraProvincia($homeProvinciaId);
        $idProvincia = (int) $provincia->id_provincia;

        $departamento = Departamento::find()
            ->where(['id_provincia' => $idProvincia])
            ->orderBy(['id_departamento' => SORT_ASC])
            ->one();

        if ($departamento === null) {
            $departamento = new Departamento();
            $departamento->id_departamento = $this->nextTableId('{{%departamentos}}', 'id_departamento');
            $departamento->nombre = 'La Capital';
            $departamento->cod_indec = '001';
            $departamento->id_provincia = $idProvincia;
            if (!$departamento->save()) {
                throw new \RuntimeException(
                    'Departamento demo: ' . json_encode($departamento->getErrors())
                );
            }
        }

        $localidad = new Localidad();
        $localidad->id_localidad = $this->nextTableId('{{%localidades}}', 'id_localidad');
        $localidad->nombre = self::PREFER_PROVINCIA_NOMBRE . ' (demo)';
        $localidad->cod_postal = 'D9901';
        $localidad->id_departamento = (int) $departamento->id_departamento;
        $localidad->id_provincia = $idProvincia;
        $localidad->cod_bahra = self::DEV_LOCALIDAD_OTRA_PROV_COD_BAHRA;
        if (!$localidad->save()) {
            throw new \RuntimeException('Localidad demo: ' . json_encode($localidad->getErrors()));
        }

        return Localidad::find()
            ->where(['id_localidad' => (int) $localidad->id_localidad])
            ->with(['departamento.provincia'])
            ->one() ?? $localidad;
    }

    private function resolveProvinciaPreferidaOtraProvincia(?int $homeProvinciaId): Provincia
    {
        $provincia = Provincia::find()
            ->where(['like', 'nombre', self::PREFER_PROVINCIA_NOMBRE, false])
            ->orderBy(['id_provincia' => SORT_ASC])
            ->one();

        if ($provincia !== null
            && ($homeProvinciaId === null || (int) $provincia->id_provincia !== $homeProvinciaId)) {
            return $provincia;
        }

        $provincia = Provincia::findOne(['cod_indec' => self::PREFER_PROVINCIA_COD_INDEC]);
        if ($provincia !== null
            && ($homeProvinciaId === null || (int) $provincia->id_provincia !== $homeProvinciaId)) {
            return $provincia;
        }

        if ($homeProvinciaId !== null && $homeProvinciaId > 0) {
            $otra = Provincia::find()
                ->where(['not', ['id_provincia' => $homeProvinciaId]])
                ->orderBy(['nombre' => SORT_ASC])
                ->one();
            if ($otra !== null) {
                return $otra;
            }
        }

        $provincia = Provincia::find()
            ->orderBy(['nombre' => SORT_ASC])
            ->one();
        if ($provincia !== null
            && ($homeProvinciaId === null || (int) $provincia->id_provincia !== $homeProvinciaId)) {
            return $provincia;
        }

        $provincia = new Provincia();
        $provincia->id_provincia = $this->nextTableId('{{%provincias}}', 'id_provincia');
        $provincia->nombre = self::PREFER_PROVINCIA_NOMBRE;
        $provincia->region_pais = 'Centro';
        $provincia->superficie = 133007;
        $provincia->cod_indec = self::PREFER_PROVINCIA_COD_INDEC;
        if (!$provincia->save()) {
            throw new \RuntimeException('Provincia demo: ' . json_encode($provincia->getErrors()));
        }

        return $provincia;
    }

    private function resolveProvinciaNombreForLocalidad(Localidad $localidad): string
    {
        $departamento = $localidad->departamento;
        if ($departamento !== null) {
            $provincia = $departamento->provincia;
            if ($provincia !== null && trim((string) $provincia->nombre) !== '') {
                return (string) $provincia->nombre;
            }

            $idProvincia = (int) $departamento->id_provincia;
            if ($idProvincia > 0) {
                $row = Provincia::findOne($idProvincia);
                if ($row !== null && trim((string) $row->nombre) !== '') {
                    return (string) $row->nombre;
                }
            }
        }

        return self::PREFER_PROVINCIA_NOMBRE;
    }

    private function nextTableId(string $table, string $column): int
    {
        $max = (new Query())->from($table)->max($column);

        return $max !== null && (int) $max > 0 ? ((int) $max) + 1 : 1;
    }
}
