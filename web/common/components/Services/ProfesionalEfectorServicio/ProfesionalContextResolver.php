<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use Yii;
use common\models\ProfesionalEfectorServicio;

/**
 * Resolver de contexto profesional (PES + id_rr_hh vía persona).
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
     * Resuelve id_rr_hh para un PES dado (misma persona que el PES).
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

        return ProfesionalEfectorServicio::resolveIdRrhhForPersona((int) $pes->id_persona);
    }
}
