<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\busquedas\ProfesionalEfectorServicioAgendaBusqueda;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
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

    public static function findOwnedByStaffContext(int $idAgenda, int $idEfector, int $staffContextId): ?ProfesionalEfectorServicioAgenda
    {
        $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($staffContextId);
        if ($idPersona === null || $idPersona <= 0) {
            return null;
        }

        $query = ProfesionalEfectorServicioAgenda::find()->alias('a');
        $query->innerJoin(
            ['pes' => ProfesionalEfectorServicio::tableName()],
            'pes.id = a.id_profesional_efector_servicio AND pes.deleted_at IS NULL'
        );
        $query->andWhere([
            'a.id' => $idAgenda,
            'a.id_efector' => $idEfector,
            'a.deleted_at' => null,
            'pes.id_persona' => $idPersona,
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

    public static function searchForStaffContext(array $queryParams, int $staffContextId, int $defaultPerPage = 20, int $maxPerPage = 100): ActiveDataProvider
    {
        $queryParams['id_profesional_contexto'] = $staffContextId;

        return self::search($queryParams, $defaultPerPage, $maxPerPage);
    }

    public static function searchForStaffContextEnEfector(
        array $queryParams,
        int $idEfector,
        int $staffContextId,
        int $defaultPerPage = 20,
        int $maxPerPage = 100
    ): ActiveDataProvider {
        $queryParams['id_efector'] = $idEfector;
        $queryParams['id_profesional_contexto'] = $staffContextId;

        return self::search($queryParams, $defaultPerPage, $maxPerPage);
    }

    /**
     * @throws BadRequestHttpException
     */
    public static function assertStaffContextEnEfector(int $staffContextId, int $idEfector): void
    {
        if ($staffContextId <= 0 || $idEfector <= 0) {
            throw new BadRequestHttpException('id_efector e id_profesional_efector_servicio deben ser válidos.');
        }
        if (!ProfesionalEfectorServicio::staffContextTienePesEnEfector($staffContextId, $idEfector)) {
            throw new BadRequestHttpException('El profesional no pertenece al efector indicado.');
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
     * @throws BadRequestHttpException
     */
    public static function assertServicioAsignadoParaStaffContextEnEfector(?int $idPesAsignado, int $staffContextId, int $idEfector): void
    {
        if ($idPesAsignado === null || $idPesAsignado <= 0) {
            return;
        }
        self::assertStaffContextEnEfector($staffContextId, $idEfector);
        $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($staffContextId);
        if ($idPersona === null || $idPersona <= 0) {
            throw new BadRequestHttpException('Servicio asignado no válido para este profesional.');
        }
        $ok = ProfesionalEfectorServicio::find()
            ->alias('pes')
            ->where([
                'pes.id_persona' => $idPersona,
                'pes.id_efector' => $idEfector,
                'pes.deleted_at' => null,
                'pes.id' => $idPesAsignado,
            ])
            ->exists();
        if (!$ok) {
            throw new BadRequestHttpException('Servicio asignado no encontrado o no corresponde a este profesional.');
        }
    }

    /**
     * Resuelve PES existente por PK en el efector y persona del contexto profesional.
     *
     * @throws BadRequestHttpException
     */
    public static function obtenerPesPorIdEnEfectorParaStaffContext(int $idPes, int $staffContextId, int $idEfector): ProfesionalEfectorServicio
    {
        $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($staffContextId);
        if ($idPersona === null || $idPersona <= 0) {
            throw new BadRequestHttpException('El profesional no pertenece al efector en sesión.');
        }
        if (!ProfesionalEfectorServicio::staffContextTienePesEnEfector($staffContextId, $idEfector)) {
            throw new BadRequestHttpException('El profesional no pertenece al efector en sesión.');
        }
        $pesDirect = ProfesionalEfectorServicio::find()
            ->where([
                'id' => $idPes,
                'id_persona' => $idPersona,
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
