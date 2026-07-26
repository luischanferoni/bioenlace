<?php

namespace common\components\Domain\Clinical\Enum;

use common\models\DiagnosticoConsulta;

/**
 * clinical_status FHIR Condition (valores alineados a DiagnosticoConsulta).
 */
final class ConditionClinicalStatus
{
    public const ACTIVE = DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
    public const RECURRENCE = DiagnosticoConsulta::CLINICAL_STATUS_RECURRENCE;
    public const RELAPSE = DiagnosticoConsulta::CLINICAL_STATUS_RELAPSE;
    public const INACTIVE = DiagnosticoConsulta::CLINICAL_STATUS_INACTIVE;
    public const REMISSION = DiagnosticoConsulta::CLINICAL_STATUS_REMISSION;
    public const RESOLVED = DiagnosticoConsulta::CLINICAL_STATUS_RESOLVED;
    public const UNKNOWN = DiagnosticoConsulta::CLINICAL_STATUS_UNKNOWN;

    /** @var list<string> */
    public const ACTIVE_LIKE = [
        self::ACTIVE,
        self::RECURRENCE,
        self::RELAPSE,
    ];

    /** @var list<string> */
    public const CLOSED_LIKE = [
        self::INACTIVE,
        self::REMISSION,
        self::RESOLVED,
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, [
            self::ACTIVE,
            self::RECURRENCE,
            self::RELAPSE,
            self::INACTIVE,
            self::REMISSION,
            self::RESOLVED,
            self::UNKNOWN,
        ], true);
    }

    public static function isActiveLike(string $status): bool
    {
        return in_array($status, self::ACTIVE_LIKE, true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        if (!self::isValid($from) || !self::isValid($to)) {
            return false;
        }
        $allowed = [
            self::ACTIVE => [self::RECURRENCE, self::RELAPSE, self::INACTIVE, self::REMISSION, self::RESOLVED],
            self::RECURRENCE => [self::ACTIVE, self::RELAPSE, self::INACTIVE, self::REMISSION, self::RESOLVED],
            self::RELAPSE => [self::ACTIVE, self::RECURRENCE, self::INACTIVE, self::REMISSION, self::RESOLVED],
            self::INACTIVE => [self::ACTIVE, self::RECURRENCE, self::RELAPSE, self::REMISSION, self::RESOLVED],
            self::REMISSION => [self::ACTIVE, self::RECURRENCE, self::RELAPSE, self::INACTIVE, self::RESOLVED],
            self::RESOLVED => [self::ACTIVE, self::RECURRENCE, self::RELAPSE],
            self::UNKNOWN => [self::ACTIVE, self::INACTIVE, self::RESOLVED, self::REMISSION],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }

    /**
     * Opciones de cierre para UI (sin preselección).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function closureOptions(): array
    {
        return [
            ['value' => self::ACTIVE, 'label' => 'Sigue activo'],
            ['value' => self::RESOLVED, 'label' => 'Resuelto'],
            ['value' => self::REMISSION, 'label' => 'En remisión'],
            ['value' => self::INACTIVE, 'label' => 'Inactivo'],
        ];
    }
}
