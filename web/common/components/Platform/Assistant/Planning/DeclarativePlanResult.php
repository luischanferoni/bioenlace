<?php

namespace common\components\Platform\Assistant\Planning;

/**
 * Plan declarativo área → tools ({@see DeclarativePlanService}).
 */
final class DeclarativePlanResult
{
    /**
     * @param list<string> $toolIds
     */
    public function __construct(
        public readonly array $toolIds,
        public readonly string $reason,
        public readonly bool $needsPlanner,
        public readonly ?string $plannerReason,
    ) {
    }
}
