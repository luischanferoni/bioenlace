<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use Yii;
use common\models\ProfesionalEfectorServicio;
use common\models\RrhhEfector;
use common\models\RrhhServicio;

/**
 * Resolver de contexto profesional (PES primary, RRHH compat).
 *
 * Pensado para centralizar bridges temporales durante migración.
 * Sin dependencias HTTP.
 */
final class ProfesionalContextResolver
{
    /**
     * Obtiene id_rr_hh desde sesión o, si no existe, desde el PES activo en sesión.
     */
    public static function resolveRrhhIdFromSessionOrPes(): int
    {
        $id = (int) (Yii::$app->user->getIdRecursoHumano() ?? 0);
        if ($id > 0) {
            return $id;
        }
        $idPes = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        if ($idPes <= 0) {
            return 0;
        }
        return self::resolveRrhhIdFromPes($idPes);
    }

    /**
     * Resuelve id_rr_hh para un PES dado (mismo efector) si existe vínculo legacy.
     */
    public static function resolveRrhhIdFromPes(int $idPes): int
    {
        if ($idPes <= 0) {
            return 0;
        }
        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null) {
            return 0;
        }
        $re = RrhhEfector::find()
            ->where([
                'id_persona' => (int) $pes->id_persona,
                'id_efector' => (int) $pes->id_efector,
                'deleted_at' => null,
            ])
            ->one();
        return $re !== null ? (int) $re->id_rr_hh : 0;
    }

    /**
     * Devuelve fila `rrhh_efector` del profesional activo (RRHH en sesión o derivado de PES).
     */
    public static function resolveRrhhEfectorFromSessionOrPes(): ?RrhhEfector
    {
        $idRrhh = self::resolveRrhhIdFromSessionOrPes();
        if ($idRrhh <= 0) {
            return null;
        }
        return RrhhEfector::findOne($idRrhh);
    }

    /**
     * Resuelve `rrhh_servicio.id` (agenda legacy) a partir de PES.
     * Útil para compat en flujos que aún persisten `id_rrhh_servicio_asignado`.
     */
    public static function resolveRrhhServicioIdFromPes(int $idPes): ?int
    {
        if ($idPes <= 0) {
            return null;
        }
        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null) {
            return null;
        }
        $legacySlot = (int) ($pes->legacy_rrhh_servicio_id ?? 0);
        if ($legacySlot > 0) {
            return $legacySlot;
        }
        $idRrhh = self::resolveRrhhIdFromPes($idPes);
        if ($idRrhh <= 0) {
            return null;
        }
        $rs = RrhhServicio::find()
            ->where([
                'id_rr_hh' => $idRrhh,
                'id_servicio' => (int) $pes->id_servicio,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->select(['id'])
            ->scalar();
        return $rs ? (int) $rs : null;
    }
}

