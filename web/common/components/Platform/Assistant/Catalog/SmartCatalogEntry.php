<?php

namespace common\components\Platform\Assistant\Catalog;

/**
 * Fila del catálogo inteligente ({@see smart-catalog.yaml}).
 */
final class SmartCatalogEntry
{
    public function __construct(
        public readonly string $id,
        public readonly bool $matchOnly,
        public readonly string $toolType,
        public readonly string $toolRef,
        public readonly string $toolId,
        public readonly string $routingResult,
        public readonly int $priority,
        /** @var list<string> */
        public readonly array $triggerTags,
        /** @var list<string> */
        public readonly array $triggerContextAreas,
        /** @var list<string> */
        public readonly array $triggerPhrases,
        /** @var list<string> */
        public readonly array $triggerKeywords,
        /** @var list<string> */
        public readonly array $requiredAnchors,
        /** @var list<string> */
        public readonly array $requiresDataFields,
        public readonly string $responseTemplate,
        public readonly string $ctaIntentId,
    ) {
    }

    public function isExecutable(): bool
    {
        if ($this->matchOnly) {
            return false;
        }

        return $this->toolRef !== '';
    }
}
