<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Clinical\Home\StaffClinicalDayListService;

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
