<?php

namespace common\components\Services\Agenda;

use common\models\Agenda_rrhh;
use common\models\busquedas\Agenda_rrhhBusqueda;
use yii\data\ActiveDataProvider;

/**
 * Listado y serialización de agendas laborales (tabla agenda_rrhh) para la API.
 */
class AgendaRrhhCrudService
{
    public static function findOwned(int $idAgenda, int $idEfector): ?Agenda_rrhh
    {
        return Agenda_rrhh::find()
            ->where(['id_agenda_rrhh' => $idAgenda, 'id_efector' => $idEfector])
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
