<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Resultado de ejecutar una métrica compilada.
 */
final class MetricExecutionResult
{
    /** @var string */
    public $metricId;

    /** @var string */
    public $outputMode;

    /** @var array<string, int|float|string|null> */
    public $aggregates;

    /** @var list<array<string, mixed>> */
    public $rows;

    /** @var list<array<string, mixed>> */
    public $groups;

    /** @var array<string, mixed> */
    public $resolvedFilters;

    /** @var bool */
    public $shortCircuitEmpty;

    /** @var array<string, mixed> */
    public $meta;

    /**
     * @param array<string, int|float|string|null> $aggregates
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $groups
     * @param array<string, mixed> $resolvedFilters
     * @param array<string, mixed> $meta
     */
    public function __construct(
        string $metricId,
        string $outputMode,
        array $aggregates = [],
        array $rows = [],
        array $groups = [],
        array $resolvedFilters = [],
        bool $shortCircuitEmpty = false,
        array $meta = []
    ) {
        $this->metricId = $metricId;
        $this->outputMode = $outputMode;
        $this->aggregates = $aggregates;
        $this->rows = $rows;
        $this->groups = $groups;
        $this->resolvedFilters = $resolvedFilters;
        $this->shortCircuitEmpty = $shortCircuitEmpty;
        $this->meta = $meta;
    }

    public function primaryAggregateValue(): int
    {
        foreach ($this->aggregates as $value) {
            return (int) $value;
        }

        return 0;
    }
}
