<?php

namespace common\components\Core\DataAccess\Filter;

use common\components\Scheduling\Service\ReservaTriageServicioMapService;
use common\models\ServiciosEfector;

/**
 * servicio_rol → ids de servicio habilitados en el efector para ese rol lógico.
 */
final class ServicioRolEfectorIdsFilterResolver implements FilterValueResolverInterface
{
    /** @var ReservaTriageServicioMapService */
    private $servicioMap;

    public function __construct(?ReservaTriageServicioMapService $servicioMap = null)
    {
        $this->servicioMap = $servicioMap ?? new ReservaTriageServicioMapService();
    }

    public function resolve(FilterValueResolverContext $ctx, array $filterDef): FilterResolvedValue
    {
        $rol = trim((string) $ctx->rawValue);
        $column = trim((string) ($filterDef['apply_column'] ?? ''));
        $op = trim((string) ($filterDef['apply_op'] ?? 'in'));
        if ($rol === '') {
            return new FilterResolvedValue(false);
        }

        $idEfector = $ctx->idEfector();
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('id_efector requerido para filtrar por servicio_rol.');
        }

        $idsEfector = ServiciosEfector::find()
            ->select('id_servicio')
            ->where(['id_efector' => $idEfector])
            ->column();
        $ids = $this->servicioMap->idsServicioParaRol($rol, array_map('intval', $idsEfector));
        $label = $this->servicioMap->getLabelForRol($rol);

        if ($ids === []) {
            return new FilterResolvedValue(
                false,
                '',
                $op,
                [],
                true,
                ['servicio_rol' => $rol, 'servicio_rol_label' => $label, 'sin_servicio_en_efector' => true]
            );
        }

        return new FilterResolvedValue(
            true,
            $column,
            $op,
            $ids,
            false,
            ['servicio_rol' => $rol, 'servicio_rol_label' => $label]
        );
    }
}
