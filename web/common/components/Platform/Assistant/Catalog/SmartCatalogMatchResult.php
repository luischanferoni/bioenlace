<?php

namespace common\components\Platform\Assistant\Catalog;

/**
 * Resultado de {@see SmartCatalogMatchService::match}.
 */
final class SmartCatalogMatchResult
{
    /**
     * @param list<array{catalog_id: string, tool_id: string, score: int, routing_result: string}> $ranked
     */
    public function __construct(
        public readonly array $ranked,
        public readonly ?SmartCatalogEntry $best,
        public readonly int $bestScore,
        public readonly bool $isClearWinner,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->best === null || $this->bestScore <= 0;
    }
}
