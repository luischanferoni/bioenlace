<?php

namespace common\components\Home\Service\Sections;

use common\components\Home\Service\StaffClinicalDayListService;

final class SurgeriesDaySectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $fecha = (string) ($context['fecha'] ?? date('Y-m-d'));
        $items = (new StaffClinicalDayListService())->cirugiasAgendadasPorEfectorYFecha($fecha);

        return [
            'items' => $items,
            'total' => count($items),
            'fecha' => $fecha,
        ];
    }
}
