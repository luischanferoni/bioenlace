<?php

namespace common\components\Domain\Person\Representation\Enum;

final class PersonRelatedStatus
{
    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const REVOKED = 'revoked';
    public const BLOCKED = 'blocked';

    public static function isOperative(string $status): bool
    {
        return $status === self::ACTIVE;
    }
}
