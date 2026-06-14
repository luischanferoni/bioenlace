<?php

namespace common\components\Domain\Organization\DataAccess\Filter;

use common\components\Platform\Core\DataAccess\Filter\FilterResolvedValue;
use common\components\Platform\Core\DataAccess\Filter\FilterValueResolverContext;
use common\components\Platform\Core\DataAccess\Filter\FilterValueResolverInterface;
use common\components\Domain\Organization\Service\Servicios\ServicioMencionLookupService;
use common\models\ServiciosEfector;

/**
 * Mención o token de servicio → ids de servicio habilitados en el efector.
 */
final class ServicioRolEfectorIdsFilterResolver implements FilterValueResolverInterface
{
    /** @var ServicioMencionLookupService */
    private $lookup;

    public function __construct(?ServicioMencionLookupService $lookup = null)
    {
        $this->lookup = $lookup ?? new ServicioMencionLookupService();
    }

    public function resolve(FilterValueResolverContext $ctx, array $filterDef): FilterResolvedValue
    {
        $token = trim((string) $ctx->rawValue);
        $column = trim((string) ($filterDef['apply_column'] ?? ''));
        $op = trim((string) ($filterDef['apply_op'] ?? 'in'));
        if ($token === '') {
            return new FilterResolvedValue(false);
        }

        $idEfector = $ctx->idEfector();
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('id_efector requerido para filtrar por servicio.');
        }

        $idsEfector = ServiciosEfector::find()
            ->select('id_servicio')
            ->where(['id_efector' => $idEfector])
            ->column();
        $idsGlobales = $this->lookup->idsDesdeMencion($token);
        $allow = array_flip(array_map('intval', $idsEfector));
        $ids = [];
        foreach ($idsGlobales as $id) {
            if (isset($allow[$id])) {
                $ids[] = $id;
            }
        }
        $label = $this->lookup->labelParaIds($idsGlobales);

        if ($ids === []) {
            return new FilterResolvedValue(
                false,
                '',
                $op,
                [],
                true,
                ['servicio_rol' => $token, 'servicio_rol_label' => $label, 'sin_servicio_en_efector' => true]
            );
        }

        return new FilterResolvedValue(
            true,
            $column,
            $op,
            $ids,
            false,
            ['servicio_rol' => $token, 'servicio_rol_label' => $label]
        );
    }
}
