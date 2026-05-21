<?php

namespace common\components\Clinical\Enum;

final class CarePlanStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const ON_HOLD = 'on-hold';
    public const REVOKED = 'revoked';
    public const COMPLETED = 'completed';
    public const ENTERED_IN_ERROR = 'entered-in-error';
    public const UNKNOWN = 'unknown';

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        $allowed = [
            self::DRAFT => [self::ACTIVE, self::ENTERED_IN_ERROR, self::REVOKED],
            self::ACTIVE => [self::ON_HOLD, self::COMPLETED, self::REVOKED, self::ENTERED_IN_ERROR],
            self::ON_HOLD => [self::ACTIVE, self::REVOKED, self::COMPLETED],
            self::COMPLETED => [],
            self::REVOKED => [],
            self::ENTERED_IN_ERROR => [],
            self::UNKNOWN => [self::ACTIVE, self::REVOKED],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }
}
