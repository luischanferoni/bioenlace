<?php

namespace common\components\Clinical\Emergency\Service;

use common\models\Guardia;
use Yii;

/**
 * Acceso staff a episodios de guardia por efector (sin revalidar identidad).
 */
final class GuardiaEfectorAccess
{
    public static function resolveIdEfector(?int $fromRequest): int
    {
        if ($fromRequest !== null && $fromRequest > 0) {
            return $fromRequest;
        }

        return (int) Yii::$app->user->getIdEfector();
    }

    public static function assertCanAccessEfector(int $idEfector): void
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException(
                'Se requiere id_efector en la solicitud o sesión operativa con efector de guardia.'
            );
        }
        if (Yii::$app->user->isSuperadmin) {
            return;
        }
        if ((int) Yii::$app->user->getIdEfector() === $idEfector) {
            return;
        }
        $efectores = Yii::$app->user->getEfectores();
        if (is_array($efectores) && array_key_exists($idEfector, $efectores)) {
            return;
        }
        throw new \InvalidArgumentException('No tiene acceso al efector indicado.');
    }

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
