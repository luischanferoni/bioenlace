<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\busquedas\ProfesionalEfectorServicioAgendaBusqueda;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\RrhhEfector;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;

/**
 * Listado, búsqueda y serialización de agendas laborales (`profesional_efector_servicio_agenda`) para la API REST.
 */
class ProfesionalEfectorServicioAgendaApiService
{
    public static function findOwnedByEfector(int $idAgenda, int $idEfector): ?ProfesionalEfectorServicioAgenda
    {
        /** @var ProfesionalEfectorServicioAgenda|null $model */
        $model = ProfesionalEfectorServicioAgenda::find()->alias('a')
            ->where(['a.id' => $idAgenda, 'a.id_efector' => $idEfector, 'a.deleted_at' => null])
            ->one();

        return $model;
    }

    public static function findOwnedByRecursoHumano(int $idAgenda, int $idEfector, int $idRrhh): ?ProfesionalEfectorServicioAgenda
    {
        $query = ProfesionalEfectorServicioAgenda::find()->alias('a');
        $query->innerJoin(
            ['pes' => ProfesionalEfectorServicio::tableName()],
            'pes.id = a.id_profesional_efector_servicio AND pes.deleted_at IS NULL'
        );
        $query->innerJoin(
            ['re' => 'rrhh_efector'],
            're.id_persona = pes.id_persona AND re.id_efector = pes.id_efector AND re.deleted_at IS NULL'
        );
        $query->andWhere([
            'a.id' => $idAgenda,
            'a.id_efector' => $idEfector,
            'a.deleted_at' => null,
            're.id_rr_hh' => $idRrhh,
        ]);

        /** @var ProfesionalEfectorServicioAgenda|null $model */
        $model = $query->one();

        return $model;
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function search(array $queryParams, int $defaultPerPage = 20, int $maxPerPage = 100): ActiveDataProvider
    {
        $search = new ProfesionalEfectorServicioAgendaBusqueda();
        $dp = $search->search($queryParams);
        $perPage = isset($queryParams['per-page']) ? (int) $queryParams['per-page'] : $defaultPerPage;
        $perPage = min($maxPerPage, max(1, $perPage));
        $dp->pagination->pageSize = $perPage;
        if (!empty($queryParams['page'])) {
            $dp->pagination->setPage(max(0, (int) $queryParams['page'] - 1));
        }

        return $dp;
    }

    public static function searchForRecursoHumano(array $queryParams, int $idRrhh, int $defaultPerPage = 20, int $maxPerPage = 100): ActiveDataProvider
    {
        $queryParams['id_rr_hh'] = $idRrhh;

        return self::search($queryParams, $defaultPerPage, $maxPerPage);
    }

    public static function searchParaRecursoHumanoEnEfector(
        array $queryParams,
        int $idEfector,
        int $idRrhh,
        int $defaultPerPage = 20,
        int $maxPerPage = 100
    ): ActiveDataProvider {
        $queryParams['id_efector'] = $idEfector;
        $queryParams['id_rr_hh'] = $idRrhh;

        return self::search($queryParams, $defaultPerPage, $maxPerPage);
    }

    /**
     * @throws BadRequestHttpException
     */
    public static function assertRecursoHumanoPerteneceAEfector(int $idRrhh, int $idEfector): void
    {
        if ($idRrhh <= 0 || $idEfector <= 0) {
            throw new BadRequestHttpException('id_efector e id_rr_hh deben ser válidos.');
        }
        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            throw new BadRequestHttpException('El recurso humano no pertenece al efector indicado.');
        }
    }

    /**
     * @throws BadRequestHttpException
     */
    public static function assertProfesionalEfectorServicioEnEfector(int $idPes, int $idEfector): ProfesionalEfectorServicio
    {
        if ($idPes <= 0 || $idEfector <= 0) {
            throw new BadRequestHttpException('id_efector e id_profesional_efector_servicio deben ser válidos.');
        }
        /** @var ProfesionalEfectorServicio|null $pes */
        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null || (int) $pes->id_efector !== $idEfector) {
            throw new BadRequestHttpException('La asignación profesional no pertenece al efector indicado.');
        }

        return $pes;
    }

    /**
     * Opcional: si el cliente envía `id_rrhh_servicio_asignado`, debe coincidir con la PK PES (compat de nombre).
     *
     * @throws BadRequestHttpException
     */
    public static function assertRrhhServicioAsignadoAlineadoConPes(
        ?int $idRrhhServicioAsignado,
        ProfesionalEfectorServicio $pes,
        int $idEfector
    ): void {
        if ($idEfector <= 0 || (int) $pes->id_efector !== $idEfector) {
            throw new BadRequestHttpException('La asignación profesional no pertenece al efector.');
        }
        $in = $idRrhhServicioAsignado !== null && $idRrhhServicioAsignado > 0 ? (int) $idRrhhServicioAsignado : 0;
        if ($in > 0 && $in !== (int) $pes->id) {
            throw new BadRequestHttpException('id_rrhh_servicio_asignado no coincide con id_profesional_efector_servicio.');
        }
    }

    /**
     * @throws BadRequestHttpException
     */
    public static function assertServicioAsignadoParaRecursoHumanoEnEfector(?int $idRrhhServicioAsignado, int $idRrhh, int $idEfector): void
    {
        if ($idRrhhServicioAsignado === null || $idRrhhServicioAsignado <= 0) {
            return;
        }
        self::assertRecursoHumanoPerteneceAEfector($idRrhh, $idEfector);
        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            throw new BadRequestHttpException('Servicio asignado no válido para este recurso humano.');
        }
        $ok = ProfesionalEfectorServicio::find()
            ->alias('pes')
            ->where([
                'pes.id_persona' => $re->id_persona,
                'pes.id_efector' => $idEfector,
                'pes.deleted_at' => null,
                'pes.id' => $idRrhhServicioAsignado,
            ])
            ->exists();
        if (!$ok) {
            throw new BadRequestHttpException('Servicio asignado no encontrado o no corresponde a este recurso humano.');
        }
    }

    /**
     * Resuelve PES existente por PK en el efector y persona del RRHH.
     *
     * @throws BadRequestHttpException
     */
    public static function obtenerPesPorIdEnEfectorParaRrhh(int $idPes, int $idRrhh, int $idEfector): ProfesionalEfectorServicio
    {
        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            throw new BadRequestHttpException('El recurso humano no pertenece al efector en sesión.');
        }
        $pesDirect = ProfesionalEfectorServicio::find()
            ->where([
                'id' => $idPes,
                'id_persona' => (int) $re->id_persona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->one();
        if ($pesDirect !== null) {
            return $pesDirect;
        }

        throw new BadRequestHttpException(
            'No hay fila PES para ese identificador. Cree la asignación con el flujo de alta PES (id_profesional_efector_servicio).'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function toApiArray(ProfesionalEfectorServicioAgenda $model): array
    {
        $row = $model->toArray();
        $pes = $model->asignacion;
        if ($pes !== null) {
            $row['id_profesional_efector_servicio'] = (int) $pes->id;
            $re = RrhhEfector::find()
                ->where(['id_persona' => $pes->id_persona, 'id_efector' => $pes->id_efector, 'deleted_at' => null])
                ->one();
            $row['id_rr_hh'] = $re !== null ? (int) $re->id_rr_hh : null;
            $row['id_profesional_efector_servicio'] = (int) $pes->id;
            $row['id_agenda_rrhh'] = (int) $model->id;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeDayFieldsForLoad(array $data): array
    {
        $days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        foreach ($days as $d) {
            if (!array_key_exists($d, $data)) {
                continue;
            }
            $v = $data[$d];
            if (is_bool($v)) {
                $data[$d] = $v ? 'SI' : 'NO';
            } elseif ($v === 1 || $v === '1') {
                $data[$d] = 'SI';
            } elseif ($v === 0 || $v === '0') {
                $data[$d] = 'NO';
            }
        }

        return $data;
    }
}
