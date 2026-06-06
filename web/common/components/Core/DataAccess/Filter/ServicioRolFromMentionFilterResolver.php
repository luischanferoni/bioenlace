<?php

namespace common\components\Core\DataAccess\Filter;

use common\components\Scheduling\Service\ReservaTriageServicioMapService;

/**
 * Mención NL → servicio_rol (normaliza; no aplica SQL directamente).
 */
final class ServicioRolFromMentionFilterResolver implements FilterValueResolverInterface
{
    /** @var ReservaTriageServicioMapService */
    private $servicioMap;

    public function __construct(?ReservaTriageServicioMapService $servicioMap = null)
    {
        $this->servicioMap = $servicioMap ?? new ReservaTriageServicioMapService();
    }

    public function resolve(FilterValueResolverContext $ctx, array $filterDef): FilterResolvedValue
    {
        $mention = trim((string) $ctx->rawValue);
        if ($mention === '') {
            return new FilterResolvedValue(false);
        }

        $rol = $this->servicioMap->resolveRolFromText($mention);
        if ($rol === null || $rol === '') {
            return new FilterResolvedValue(false, '', 'eq', null, false, ['mention' => $mention]);
        }

        $normalizeTo = trim((string) ($filterDef['normalize_to'] ?? 'servicio_rol'));

        return new FilterResolvedValue(
            false,
            '',
            'eq',
            $rol,
            false,
            ['normalize_to' => $normalizeTo, 'servicio_rol' => $rol, 'mention' => $mention]
        );
    }
}
