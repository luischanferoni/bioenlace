<?php

namespace common\components\Domain\Clinical\Encounter\Domain;

/**
 * verification_status FHIR Condition.
 */
final class ConditionVerificationStatus
{
    public const UNCONFIRMED = 'UNCONFIRMED';
    public const PROVISIONAL = 'PROVISIONAL';
    public const DIFFERENTIAL = 'DIFFERENTIAL';
    public const CONFIRMED = 'CONFIRMED';
    public const REFUTED = 'REFUTED';
    public const ENTERED_IN_ERROR = 'ENTERED_IN_ERROR';

    /** @var array<string, string> */
    public const LABELS = [
        self::UNCONFIRMED => 'Sin confirmar',
        self::PROVISIONAL => 'Presuntivo',
        self::DIFFERENTIAL => 'Diferencial',
        self::CONFIRMED => 'Confirmado',
        self::REFUTED => 'Refutado',
        self::ENTERED_IN_ERROR => 'Ingresado por error',
    ];
}
