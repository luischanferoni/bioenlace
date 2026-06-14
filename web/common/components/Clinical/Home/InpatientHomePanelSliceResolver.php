<?php

namespace common\components\Clinical\Home;

use common\components\Ui\Home\Service\HomePanelStaffPanelSliceResolverInterface;
use common\models\Clinical\Encounter;
use common\models\Servicio;
use Yii;

/**
 * Internación (IMP): quirófano vs piso según servicio clínico en sesión operativa.
 */
final class InpatientHomePanelSliceResolver implements HomePanelStaffPanelSliceResolverInterface
{
    public function applies(string $encounterClass): bool
    {
        return $encounterClass === Encounter::ENCOUNTER_CLASS_IMP;
    }

    public function resolve(array $staffPanelDef): array
    {
        $idServicio = (int) Yii::$app->user->getServicioActual();
        $key = ($idServicio > 0 && Servicio::esServicioAgendaQuirurgica($idServicio))
            ? 'imp_surgical'
            : 'imp_floor';

        return [
            'layout' => $staffPanelDef['layout'] ?? 'clinical_list',
            'title' => $staffPanelDef['title'] ?? 'Internación',
            'sections' => $staffPanelDef[$key]['sections'] ?? [],
        ];
    }
}
