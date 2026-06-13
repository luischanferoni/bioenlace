<?php

namespace common\components\Clinical\Inpatient\Service;

use common\models\InfraestructuraCama;
use common\models\InfraestructuraPiso;
use common\models\SegNivelInternacion;

/**
 * Pertinencia geográfica internación ↔ efector (camas, pisos).
 * Autorización por efector: políticas `Internacion.*` + {@see EfectorDomainAccessService}.
 */
final class InternacionEfectorAccess
{
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
