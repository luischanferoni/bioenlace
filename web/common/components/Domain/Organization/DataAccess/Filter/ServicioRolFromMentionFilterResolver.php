<?php

namespace common\components\Domain\Organization\DataAccess\Filter;

use common\components\Platform\Core\DataAccess\Filter\FilterResolvedValue;
use common\components\Platform\Core\DataAccess\Filter\FilterValueResolverContext;
use common\components\Platform\Core\DataAccess\Filter\FilterValueResolverInterface;
use common\components\Domain\Organization\Service\Servicios\ServicioMencionLookupService;

/**
 * Mención NL → token de filtro de servicio (normaliza; no aplica SQL directamente).
 */
final class ServicioRolFromMentionFilterResolver implements FilterValueResolverInterface
{
    /** @var ServicioMencionLookupService */
    private $lookup;

    public function __construct(?ServicioMencionLookupService $lookup = null)
    {
        $this->lookup = $lookup ?? new ServicioMencionLookupService();
    }

    public function resolve(FilterValueResolverContext $ctx, array $filterDef): FilterResolvedValue
    {
        $mention = trim((string) $ctx->rawValue);
        if ($mention === '') {
            return new FilterResolvedValue(false);
        }

        if ($this->lookup->idsDesdeMencion($mention) === []) {
            return new FilterResolvedValue(false, '', 'eq', null, false, ['mention' => $mention]);
        }

        $normalizeTo = trim((string) ($filterDef['normalize_to'] ?? 'servicio_rol'));

        return new FilterResolvedValue(
            false,
            '',
            'eq',
            $mention,
            false,
            ['normalize_to' => $normalizeTo, 'servicio_rol' => $mention, 'mention' => $mention]
        );
    }
}
