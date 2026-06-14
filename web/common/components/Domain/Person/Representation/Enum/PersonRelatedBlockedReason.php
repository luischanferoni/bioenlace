<?php

namespace common\components\Domain\Person\Representation\Enum;

final class PersonRelatedBlockedReason
{
    public const COURT_ORDER = 'court_order';
    public const CUSTODY_DISPUTE = 'custody_dispute';
    public const OTHER = 'other';
}
