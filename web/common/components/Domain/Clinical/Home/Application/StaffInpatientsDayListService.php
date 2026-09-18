<?php

namespace common\components\Domain\Clinical\Home\Application;

use Yii;
use common\models\Organization\InfraestructuraPiso;

/**
 * Listado de internados del efector para panel Home clínico (IMP piso).
 */
final class StaffInpatientsDayListService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function internadosPorEfector(): array
    {
        $idEfector = Yii::$app->user->getIdEfector();
        if (!$idEfector) {
            return [];
        }

        return InfraestructuraPiso::getInternadosPorEfector($idEfector);
    }
}
