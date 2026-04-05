<?php

namespace common\components\Services\Agenda;

use common\models\Agenda_rrhh;
use common\models\busquedas\Agenda_rrhhBusqueda;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;

/**
 * Listado y serialización de agendas laborales (tabla agenda_rrhh) para la API.
 *
 * Una fila = agenda de un servicio (vía {@see Agenda_rrhh::id_rrhh_servicio_asignado}) para un RRHH en un efector.
 */
class AgendaRrhhCrudService
{
    /**
     * Agenda en el efector (cualquier RRHH). Uso: personal con permiso para-recurso (CRUD sobre terceros).
     */
    public static function findOwnedByEfector(int $idAgenda, int $idEfector): ?Agenda_rrhh
    {
        return Agenda_rrhh::find()
            ->where(['id_agenda_rrhh' => $idAgenda, 'id_efector' => $idEfector])
            ->one();
    }

    /**
     * Agenda del profesional autenticado (mismo efector y mismo id_rr_hh).
     */
    public static function findOwnedByProfesional(int $idAgenda, int $idEfector, int $idRrhh): ?Agenda_rrhh
    {
        return Agenda_rrhh::find()
            ->where([
                'id_agenda_rrhh' => $idAgenda,
                'id_efector' => $idEfector,
                'id_rr_hh' => $idRrhh,
            ])
            ->one();
    }

    /**
     * @param array $queryParams queryParams de Yii (incl. filtros de {@see Agenda_rrhhBusqueda}, page, per-page)
     */
    public static function search(array $queryParams, int $defaultPerPage = 20, int $maxPerPage = 100): ActiveDataProvider
    {
        $search = new Agenda_rrhhBusqueda();
        $dp = $search->search($queryParams);
        $perPage = isset($queryParams['per-page']) ? (int) $queryParams['per-page'] : $defaultPerPage;
        $perPage = min($maxPerPage, max(1, $perPage));
        $dp->pagination->pageSize = $perPage;
        if (!empty($queryParams['page'])) {
            $dp->pagination->setPage(max(0, (int) $queryParams['page'] - 1));
        }

        return $dp;
    }

    /**
     * Listado acotado al RRHH del profesional (ignora id_rr_hh en query si viniera malicioso).
     */
    public static function searchForProfesional(array $queryParams, int $idRrhh, int $defaultPerPage = 20, int $maxPerPage = 100): ActiveDataProvider
    {
        $queryParams['id_rr_hh'] = $idRrhh;

        return self::search($queryParams, $defaultPerPage, $maxPerPage);
    }

    /**
     * Listado forzando efector y RRHH (staff). Los params no pueden cambiar el ámbito vía id_rr_hh/id_efector en query.
     */
    public static function searchParaRecurso(
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
    public static function assertRrhhPerteneceAEfector(int $idRrhh, int $idEfector): void
    {
        if ($idRrhh <= 0 || $idEfector <= 0) {
            throw new BadRequestHttpException('id_efector e id_rr_hh deben ser válidos.');
        }
        $re = RrhhEfector::findOne($idRrhh);
        if ($re === null || (int) $re->id_efector !== $idEfector) {
            throw new BadRequestHttpException('El recurso humano no pertenece al efector indicado.');
        }
    }

    /**
     * Valida que el servicio asignado pertenezca al RRHH y que el RRHH pertenezca al efector.
     *
     * @throws BadRequestHttpException
     */
    public static function assertServicioAsignadoParaRrhhEfector(?int $idRrhhServicioAsignado, int $idRrhh, int $idEfector): void
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
        $re = RrhhEfector::findOne($idRrhh);
        if ($re === null || (int) $re->id_efector !== $idEfector) {
            throw new BadRequestHttpException('El recurso humano no pertenece al efector en sesión.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function toApiArray(Agenda_rrhh $model): array
    {
        return $model->toArray(
            [],
            ['rrhh', 'rrhh.persona', 'tipo_dia', 'rrhhServicioAsignado']
        );
    }

    /**
     * Normaliza días enviados como boolean/entero en JSON al enum SI/NO de la BD.
     *
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
