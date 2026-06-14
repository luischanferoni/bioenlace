<?php

namespace common\components\Platform\Core\DataAccess\Filter;

/**
 * Valor resuelto de un filtro allowlisted antes de aplicarlo a la query.
 */
final class FilterResolvedValue
{
    /** @var bool */
    public $apply;

    /** @var string */
    public $column;

    /** @var string */
    public $op;

    /** @var mixed */
    public $value;

    /** @var bool Si true, la métrica debe devolver vacío/cero sin ejecutar agregación completa */
    public $shortCircuitEmpty;

    /** @var array<string, mixed> */
    public $meta;

    /**
     * @param mixed $value
     * @param array<string, mixed> $meta
     */
    public function __construct(
        bool $apply,
        string $column = '',
        string $op = 'eq',
        $value = null,
        bool $shortCircuitEmpty = false,
        array $meta = []
    ) {
        $this->apply = $apply;
        $this->column = $column;
        $this->op = $op;
        $this->value = $value;
        $this->shortCircuitEmpty = $shortCircuitEmpty;
        $this->meta = $meta;
    }
}
