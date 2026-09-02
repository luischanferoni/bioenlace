<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogEntry;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;

/**
 * Resultado completo de {@see SmartCatalogRoutingService::evaluate}.
 */
final class SmartCatalogRoutingEvaluation
{
    /**
     * @param array<string, mixed> $firstIa
     */
    public function __construct(
        public readonly array $firstIa,
        public readonly SmartCatalogMatchResult $match,
        public readonly SmartCatalogRoutingDecision $decision,
        public readonly DeclarativePlanResult $declarativePlan,
    ) {
    }

    public function bestEntry(): ?SmartCatalogEntry
    {
        return $this->decision->catalogEntry;
    }
}
