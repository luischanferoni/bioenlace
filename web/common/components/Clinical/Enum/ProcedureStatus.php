<?php

namespace common\components\Clinical\Enum;

final class ProcedureStatus
{
    public const PREPARATION = 'preparation';
    public const IN_PROGRESS = 'in-progress';
    public const NOT_DONE = 'not-done';
    public const ON_HOLD = 'on-hold';
    public const STOPPED = 'stopped';
    public const COMPLETED = 'completed';
    public const ENTERED_IN_ERROR = 'entered-in-error';
    public const UNKNOWN = 'unknown';
}
