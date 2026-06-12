<?php

namespace common\components\Home\Service\Sections;

use common\components\Home\Service\StaffClinicalDayListService;

final class InpatientsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $items = (new StaffClinicalDayListService())->internadosPorEfector();

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }
}
