<?php

namespace common\components\Domain\Clinical\Prescription\Enum;

final class PrescriptionEventType
{
    public const DRAFT_CREATED = 'draft_created';
    public const ISSUED = 'issued';
    public const CANCELLED = 'cancelled';
    public const VIEWED = 'viewed';
    public const REPOSITORY_SYNC = 'repository_sync';
}
