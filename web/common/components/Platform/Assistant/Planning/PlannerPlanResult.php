<?php

namespace common\components\Platform\Assistant\Planning;

/**
 * Output normalizado de la IA planificadora.
 */
final class PlannerPlanResult
{
    /**
     * @param list<string> $toolIdsOrdered
     */
    public function __construct(
        public readonly array $toolIdsOrdered,
        public readonly string $rationale,
    ) {
    }
}
