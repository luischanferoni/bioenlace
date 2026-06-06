<?php

namespace common\components\Core\DataAccess\Filter;

use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\AuthorizedQuery;

/**
 * Contexto para resolver valores de filtros declarativos.
 */
final class FilterValueResolverContext
{
    /** @var AuthorizedQuery */
    public $authorized;

    /** @var AttributeGroupCatalog */
    public $catalog;

    /** @var string */
    public $filterKey;

    /** @var mixed */
    public $rawValue;

    /** @var array<string, mixed> */
    public $allFilters;

    /**
     * @param mixed $rawValue
     * @param array<string, mixed> $allFilters
     */
    public function __construct(
        AuthorizedQuery $authorized,
        AttributeGroupCatalog $catalog,
        string $filterKey,
        $rawValue,
        array $allFilters
    ) {
        $this->authorized = $authorized;
        $this->catalog = $catalog;
        $this->filterKey = $filterKey;
        $this->rawValue = $rawValue;
        $this->allFilters = $allFilters;
    }

    public function idEfector(): int
    {
        return (int) ($this->authorized->scope->idEfector ?? 0);
    }
}
