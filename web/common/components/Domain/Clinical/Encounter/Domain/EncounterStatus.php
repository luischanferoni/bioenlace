<?php

namespace common\components\Domain\Clinical\Encounter\Domain;

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

    public static function normalize(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return self::UNKNOWN;
        }

        return $status;
    }

    public static function isTerminal(string $status): bool
    {
        $status = self::normalize($status);

        return in_array($status, [self::FINISHED, self::CANCELLED, self::ENTERED_IN_ERROR], true);
    }

    public static function isOpen(string $status): bool
    {
        $status = self::normalize($status);

        return in_array($status, [self::PLANNED, self::IN_PROGRESS, self::ON_HOLD, self::UNKNOWN], true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        $from = self::normalize($from);
        $to = self::normalize($to);
        if ($from === $to) {
            return true;
        }
        $allowed = [
            self::PLANNED => [self::IN_PROGRESS, self::CANCELLED, self::ENTERED_IN_ERROR],
            self::IN_PROGRESS => [self::FINISHED, self::ON_HOLD, self::CANCELLED, self::ENTERED_IN_ERROR],
            self::ON_HOLD => [self::IN_PROGRESS, self::CANCELLED, self::FINISHED],
            self::FINISHED => [],
            self::CANCELLED => [],
            self::ENTERED_IN_ERROR => [],
            self::UNKNOWN => [self::IN_PROGRESS, self::PLANNED, self::CANCELLED, self::ENTERED_IN_ERROR],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }
}
