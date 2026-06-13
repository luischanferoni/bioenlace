<?php

namespace common\components\Clinical\Inpatient\Service;

use common\components\Organization\Service\Efectores\OrganizationEfectorAccess;
use common\models\InfraestructuraCama;
use common\models\InfraestructuraPiso;
use common\models\SegNivelInternacion;

/**
 * Utilidades de internación por efector (camas, pisos, pertenencia geográfica).
 * Para autorización por efector preferir políticas `Internacion.*` / {@see OrganizationEfectorAccess}.
 */
final class InternacionEfectorAccess
{
    /**
     * @deprecated Use {@see OrganizationEfectorAccess::resolveIdEfector()} o {@see EfectorDomainAccessService}.
     */
    public static function resolveIdEfector(?int $fromRequest): int
    {
        return OrganizationEfectorAccess::resolveIdEfector($fromRequest);
    }

    /**
     * @deprecated Use políticas de dominio (`Clinical.staff_efector`, `Internacion.*`, etc.).
     */
    public static function assertCanAccessEfector(int $idEfector): void
    {
        OrganizationEfectorAccess::assertCanAccessEfector($idEfector);
    }

    public static function assertInternacionEnEfector(SegNivelInternacion $internacion, int $idEfector): void
    {
        if (!self::internacionPerteneceEfector($internacion, $idEfector)) {
            throw new \InvalidArgumentException('La internación no pertenece al efector indicado.');
        }
    }

    public static function assertCamaEnEfector(InfraestructuraCama $cama, int $idEfector): void
    {
        $piso = $cama->sala->piso ?? null;
        if ($piso === null || (int) $piso->id_efector !== $idEfector) {
            throw new \InvalidArgumentException('La cama no pertenece al efector indicado.');
        }
    }

    public static function internacionPerteneceEfector(SegNivelInternacion $internacion, int $idEfector): bool
    {
        $cama = $internacion->cama;
        if ($cama === null) {
            return false;
        }
        $piso = $cama->sala->piso ?? null;

        return $piso !== null && (int) $piso->id_efector === $idEfector;
    }

    public static function idEfectorDeCama(int $idCama): ?int
    {
        $cama = InfraestructuraCama::findOne($idCama);
        if ($cama === null) {
            return null;
        }
        $piso = $cama->sala->piso ?? null;

        return $piso !== null ? (int) $piso->id_efector : null;
    }

    /**
     * @return InfraestructuraPiso[]
     */
    public static function pisosDelEfector(int $idEfector): array
    {
        return (new InfraestructuraPiso())->pisosPorEfector($idEfector);
    }
}
