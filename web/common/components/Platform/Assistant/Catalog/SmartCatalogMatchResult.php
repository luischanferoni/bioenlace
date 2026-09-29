<?php

namespace common\components\Platform\Assistant\Catalog;

/**
 * Resultado vacío de match legacy (compat evaluación / planificadora).
 * El discovery real vive en {@see DiscoveryIndex}.
 */
final class SmartCatalogMatchResult
{
    /**
     * @param list<array{catalog_id: string, tool_id: string, score: int, routing_result: string}> $ranked
     */
    public function __construct(
        public readonly array $ranked = [],
        public readonly int $bestScore = 0,
        public readonly bool $isClearWinner = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->ranked === [] || $this->bestScore <= 0;
    }
}
