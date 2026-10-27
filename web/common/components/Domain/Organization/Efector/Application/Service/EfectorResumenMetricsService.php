<?php

namespace common\components\Domain\Organization\Efector\Application\Service;

use common\models\Organization\Efector;

/**
 * Métricas de cabecera del efector para paneles de inicio (sin datos personales).
 */
final class EfectorResumenMetricsService
{
    /**
     * @return array{profesionales_activos: int, servicios: int}|array{}
     */
    public function resumen(int $idEfector): array
    {
        if ($idEfector <= 0) {
            return [];
        }

        $efector = Efector::findOne($idEfector);
        if ($efector === null) {
            return [];
        }

        return [
            'profesionales_activos' => (int) $efector->getProfesionales()->count(),
            'servicios' => (int) $efector->getServicios()->count(),
        ];
    }
}
