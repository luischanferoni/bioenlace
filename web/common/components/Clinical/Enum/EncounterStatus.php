<?php

namespace common\components\Clinical\Enum;

/**
 * Subconjunto FHIR EncounterStatus usado en Bioenlace.
 */
final class EncounterStatus
{
    public const PLANNED = 'planned';
    public const IN_PROGRESS = 'in-progress';
    public const ON_HOLD = 'on-hold';
    public const FINISHED = 'finished';
    public const CANCELLED = 'cancelled';
    public const ENTERED_IN_ERROR = 'entered-in-error';
    public const UNKNOWN = 'unknown';

    /** Valores legacy pre-FHIR (tabla `consultas`). */
    public const LEGACY_EN_PROGRESO = 'EN_PROGRESO';
    public const LEGACY_FINALIZADA = 'FINALIZADA';
    public const LEGACY_CANCELADA = 'CANCELADA';
    public const LEGACY_PENDIENTE = 'PENDIENTE';

    public static function fromLegacy(string $legacy): string
    {
        switch ($legacy) {
            case self::LEGACY_FINALIZADA:
                return self::FINISHED;
            case self::LEGACY_CANCELADA:
                return self::CANCELLED;
            case self::LEGACY_PENDIENTE:
                return self::PLANNED;
            default:
                return self::IN_PROGRESS;
        }
    }
}
