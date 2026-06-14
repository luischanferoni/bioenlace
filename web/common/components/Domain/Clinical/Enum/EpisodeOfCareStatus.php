<?php

namespace common\components\Domain\Clinical\Enum;

final class EpisodeOfCareStatus
{
    public const PLANNED = 'planned';
    public const ACTIVE = 'active';
    public const ON_HOLD = 'on-hold';
    public const FINISHED = 'finished';
    public const CANCELLED = 'cancelled';
    public const ENTERED_IN_ERROR = 'entered-in-error';
}
