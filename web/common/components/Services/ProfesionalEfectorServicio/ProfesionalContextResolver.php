<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use Yii;

/**
 * Resolver de contexto profesional (PES / sesión staff).
 * Sin dependencias HTTP.
 */
final class ProfesionalContextResolver
{
    /**
     * Identificador de contexto staff en dominio actual: suele ser PK de profesional_efector_servicio.
     */
    public static function resolveProfesionalColumnIdFromSessionOrPes(): int
    {
        $id = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        if ($id > 0) {
            return $id;
        }
        $idPes = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);

        return self::resolveProfesionalColumnIdFromPes($idPes);
    }

    public static function resolveProfesionalColumnIdFromPes(int $idPes): int
    {
        return $idPes > 0 ? $idPes : 0;
    }
}
