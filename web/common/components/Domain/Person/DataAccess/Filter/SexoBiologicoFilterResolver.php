<?php

namespace common\components\Domain\Person\DataAccess\Filter;

use common\components\Platform\Core\DataAccess\Filter\FilterResolvedValue;
use common\components\Platform\Core\DataAccess\Filter\FilterValueResolverContext;
use common\components\Platform\Core\DataAccess\Filter\FilterValueResolverInterface;
 * sexo_biologico literal o sinónimo NL → código 1|2.
 */
final class SexoBiologicoFilterResolver implements FilterValueResolverInterface
{
    public function resolve(FilterValueResolverContext $ctx, array $filterDef): FilterResolvedValue
    {
        $raw = $ctx->rawValue;
        $column = trim((string) ($filterDef['apply_column'] ?? ''));
        $op = trim((string) ($filterDef['apply_op'] ?? 'eq'));
        $code = null;

        if (is_numeric($raw)) {
            $code = (int) $raw;
            if (!in_array($code, [1, 2], true)) {
                $code = null;
            }
        } else {
            $code = $ctx->catalog->resolveSexoBiologicoFromMention((string) $raw);
        }

        if ($code === null) {
            return new FilterResolvedValue(false);
        }

        $label = $code === 2 ? 'masculino' : 'femenino';

        return new FilterResolvedValue(
            true,
            $column,
            $op,
            $code,
            false,
            ['sexo_biologico' => $code, 'sexo_biologico_label' => $label]
        );
    }
}
