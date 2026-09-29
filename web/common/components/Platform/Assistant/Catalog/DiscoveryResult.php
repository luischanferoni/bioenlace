<?php

namespace common\components\Platform\Assistant\Catalog;

/**
 * Resultado del índice único de discovery (intents YAML + artículos BD).
 */
final class DiscoveryResult
{
    /**
     * @param list<array{intent_id: string, score: int, states: list<array{id: string, description: string}>}> $intentHits
     * @param list<array{topic: string, score: int, title: string}> $articleHits
     */
    public function __construct(
        public readonly array $intentHits = [],
        public readonly array $articleHits = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->intentHits === [] && $this->articleHits === [];
    }

    /**
     * @return list<string>
     */
    public function intentIds(int $max = 4): array
    {
        $ids = [];
        foreach ($this->intentHits as $hit) {
            $id = trim((string) ($hit['intent_id'] ?? ''));
            if ($id === '' || in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
            if (count($ids) >= max(1, $max)) {
                break;
            }
        }

        return $ids;
    }

    public function primaryArticleTopic(): string
    {
        return trim((string) ($this->articleHits[0]['topic'] ?? ''));
    }
}
