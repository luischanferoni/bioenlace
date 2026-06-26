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
 * Idempotente por codigo_sisa reservado.
 */
final class EfectorDemoSeedService
{
    public const SEED_MARKER = 'seed:efector-demo-contexto';

    /** Efector público provincial (Santa Fe, cod_indec 82). */
    public const COD_SISA_PUBLIC_OTRA_PROV = 'DEV99001SFPUB';

    /** Clínica privada (Santiago del Estero, cod_indec 86). */
    public const COD_SISA_PRIVATE = 'DEV99002PRIV';

    public const PROVINCIA_PUBLIC_COD_INDEC = '82';

    public const PROVINCIA_PRIVATE_COD_INDEC = '86';

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
    public function upsertPublicOtraProvincia(): array
    {
        $localidad = $this->findFirstLocalidadInProvincia(self::PROVINCIA_PUBLIC_COD_INDEC);

        return $this->upsertEfector(
            self::COD_SISA_PUBLIC_OTRA_PROV,
            '[DEV] CAP Santa Fe Demo',
            'Provincial',
            'CAP',
            'Av. San Martín 100',
            'Provincial',
            '0342-4000000',
            (int) $localidad->id_localidad,
            (string) $localidad->departamento->provincia->nombre
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function upsertClinicaPrivada(): array
    {
        $localidad = $this->findFirstLocalidadInProvincia(self::PROVINCIA_PRIVATE_COD_INDEC);

        return $this->upsertEfector(
            self::COD_SISA_PRIVATE,
            '[DEV] Clínica Privada Demo',
            'Privado',
            'CLIN',
            'Av. Independencia 250',
            'Privado',
            '0385-4220000',
            (int) $localidad->id_localidad,
            (string) $localidad->departamento->provincia->nombre
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

    private function findFirstLocalidadInProvincia(string $codIndecProvincia): Localidad
    {
        $provincia = Provincia::findOne(['cod_indec' => $codIndecProvincia]);
        if ($provincia === null) {
            throw new \InvalidArgumentException(
                "Provincia cod_indec={$codIndecProvincia} no encontrada. Verificá el catálogo geográfico."
            );
        }

        $localidad = Localidad::find()
            ->alias('l')
            ->innerJoin(
                ['d' => Departamento::tableName()],
                'd.id_departamento = l.id_departamento'
            )
            ->where(['d.id_provincia' => (int) $provincia->id_provincia])
            ->with(['departamento.provincia'])
            ->orderBy(['l.id_localidad' => SORT_ASC])
            ->one();

        if ($localidad === null) {
            throw new \RuntimeException(
                'No hay localidades en provincia '
                . $provincia->nombre
                . " (cod_indec={$codIndecProvincia})."
            );
        }

        return $localidad;
    }
}
