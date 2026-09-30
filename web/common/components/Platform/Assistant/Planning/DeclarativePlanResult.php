<?php

namespace common\components\Platform\Assistant\Planning;

/**
 * Plan declarativo de tools ({@see DeclarativePlanService}).
 */
final class DeclarativePlanResult
{
    /**
     * @param list<string> $toolIds
     */
    public function __construct(
        public readonly array $toolIds,
        public readonly string $reason,
    ) {
    }
}
