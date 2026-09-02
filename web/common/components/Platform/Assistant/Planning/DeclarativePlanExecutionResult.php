<?php

namespace common\components\Platform\Assistant\Planning;

/**
 * Resultado de {@see DeclarativePlanExecutor::execute}.
 */
final class DeclarativePlanExecutionResult
{
    /**
     * @param list<array{tool_id: string, ms: int, chars: int, had_null_fields: bool}> $executedTools
     */
    public function __construct(
        public readonly string $scopedSystemRecords,
        public readonly string $articleBlock,
        public readonly array $executedTools,
        public readonly bool $hasUsefulData,
    ) {
    }

    public static function merge(self $primary, self $secondary): self
    {
        $scoped = trim($primary->scopedSystemRecords);
        $secondaryScoped = trim($secondary->scopedSystemRecords);
        if ($secondaryScoped !== '') {
            $scoped = $scoped === '' ? $secondaryScoped : $scoped . "\n\n" . $secondaryScoped;
        }

        $article = trim($primary->articleBlock) !== '' ? $primary->articleBlock : $secondary->articleBlock;

        return new self(
            $scoped,
            $article,
            array_merge($primary->executedTools, $secondary->executedTools),
            $primary->hasUsefulData || $secondary->hasUsefulData,
        );
    }
}
