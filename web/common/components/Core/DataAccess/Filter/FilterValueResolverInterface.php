<?php

namespace common\components\Core\DataAccess\Filter;

use common\components\Core\DataAccess\FilterResolvedValue;

/**
 * Resuelve un valor de filtro declarativo a condición SQL parametrizable.
 */
interface FilterValueResolverInterface
{
    /**
     * @param array<string, mixed> $filterDef definición YAML del filtro
     */
    public function resolve(FilterValueResolverContext $ctx, array $filterDef): FilterResolvedValue;
}
