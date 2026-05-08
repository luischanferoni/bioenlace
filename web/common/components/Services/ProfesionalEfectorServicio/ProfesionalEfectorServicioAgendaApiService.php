<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\busquedas\ProfesionalEfectorServicioAgendaBusqueda;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
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
    public static function assertServicioAsignadoParaRecursoHumanoEnEfector(?int $idRrhhServicioAsignado, int $idRrhh, int $idEfector): void
    {
        if ($idRrhhServicioAsignado === null || $idRrhhServicioAsignado <= 0) {
            return;
        }
        $rs = RrhhServicio::findOne($idRrhhServicioAsignado);
        if ($rs === null) {
            throw new BadRequestHttpException('Servicio asignado no encontrado.');
        }
        if ((int) $rs->id_rr_hh !== $idRrhh) {
            throw new BadRequestHttpException('El servicio asignado no corresponde a este recurso humano.');
        }
        self::assertRecursoHumanoPerteneceAEfector($idRrhh, $idEfector);
    }

    /**
     * Obtiene o crea la fila PES coherente con rrhh_servicio en el efector.
     *
     * @throws BadRequestHttpException
     */
    public static function obtenerOCrearPesParaRrhhServicioEnEfector(int $idRrhhServicio, int $idRrhh, int $idEfector): ProfesionalEfectorServicio
    {
        $rs = RrhhServicio::findOne(['id' => $idRrhhServicio, 'deleted_at' => null]);
        if ($rs === null || (int) $rs->id_rr_hh !== $idRrhh) {
            throw new BadRequestHttpException('Servicio asignado no válido para este recurso humano.');
        }
        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            throw new BadRequestHttpException('El recurso humano no pertenece al efector en sesión.');
        }
        $pes = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio(
            (int) $re->id_persona,
            $idEfector,
            (int) $rs->id_servicio
        );
        if ($pes === null) {
            $pes = new ProfesionalEfectorServicio();
            $pes->id_persona = (int) $re->id_persona;
            $pes->id_efector = $idEfector;
            $pes->id_servicio = (int) $rs->id_servicio;
            $pes->legacy_rrhh_servicio_id = (int) $rs->id;
            if (!$pes->save()) {
                throw new BadRequestHttpException('No se pudo crear la asignación profesional-efector-servicio.');
            }
        }

        return $pes;
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
            $row['id_rrhh_servicio_asignado'] = $pes->resolveRrhhServicioAsignadoIdForTurnoCompat();
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
