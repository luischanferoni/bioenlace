<?php

namespace common\components\Clinical\Emergency\Service;

use common\components\Organization\Service\Efectores\OrganizationEfectorAccess;
use common\models\Guardia;

/**
 * Utilidades de guardia por efector (PES, pertenencia de episodio).
 * Para autorización por efector preferir políticas `GuardiaEpisode.*` / {@see OrganizationEfectorAccess}.
 */
final class GuardiaEfectorAccess
{
    /**
     * @deprecated Use {@see OrganizationEfectorAccess::resolveIdEfector()} o {@see EfectorDomainAccessService}.
     */
    public static function resolveIdEfector(?int $fromRequest): int
    {
        return OrganizationEfectorAccess::resolveIdEfector($fromRequest);
    }

    /**
     * @deprecated Use políticas de dominio (`GuardiaEpisode.view_board`, etc.).
     */
    public static function assertCanAccessEfector(int $idEfector): void
    {
        OrganizationEfectorAccess::assertCanAccessEfector($idEfector);
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
        $raw = \Yii::$app->user->getIdProfesionalEfectorServicio();
        if ($raw === null || $raw === '') {
            return null;
        }
        $pes = (int) $raw;

        return $pes > 0 ? $pes : null;
    }
}
