<?php

namespace common\components\Domain\Clinical\Encounter\Domain;

/**
 * clinical_status FHIR Condition.
 */
final class ConditionClinicalStatus
{
    public const ACTIVE = 'ACTIVE';
    public const RECURRENCE = 'RECURRENCE';
    public const RELAPSE = 'RELAPSE';
    public const INACTIVE = 'INACTIVE';
    public const REMISSION = 'REMISSION';
    public const RESOLVED = 'RESOLVED';
    public const UNKNOWN = 'UNKNOWN';

    /** @var array<string, string> */
    public const LABELS = [
        self::ACTIVE => 'Activo',
        self::RECURRENCE => 'Activo-Reaparición',
        self::RELAPSE => 'Activo-Recaida',
        self::INACTIVE => 'Inactivo',
        self::REMISSION => 'Inactivo-Remisión',
        self::RESOLVED => 'Inactivo-Resuelto',
    ];

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

    public static function isClosedLike(string $status): bool
    {
        return in_array(strtoupper(trim($status)), self::CLOSED_LIKE, true);
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
