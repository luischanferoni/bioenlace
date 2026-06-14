<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\models\Guardia;
use Yii;

/**
 * Contexto operativo guardia: PES de sesión y pertenencia episodio ↔ efector.
 * Autorización por efector: políticas `GuardiaEpisode.*` + {@see EfectorAccessService}.
 */
final class GuardiaEfectorAccess
{
    public static function assertGuardiaEnEfector(Guardia $guardia, int $idEfector): void
    {
        if ((int) $guardia->id_efector !== $idEfector) {
            throw new \InvalidArgumentException('La guardia no pertenece al efector indicado.');
        }
    }

    public static function resolvePesId(?int $fromBody): ?int
    {
        if ($fromBody !== null && $fromBody > 0) {
            return $fromBody;
        }
        $raw = Yii::$app->user->getIdProfesionalEfectorServicio();
        if ($raw === null || $raw === '') {
            return null;
        }
        $pes = (int) $raw;

        return $pes > 0 ? $pes : null;
    }
}
