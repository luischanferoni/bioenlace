<?php

namespace common\components\Organization\Service\Efectores;

use Yii;

/**
 * Acceso staff a datos organizacionales por efector (sin revalidar identidad JWT).
 */
final class OrganizationEfectorAccess
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
                'Se requiere id_efector en la solicitud o sesión operativa con efector.'
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
}
