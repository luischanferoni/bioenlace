<?php

namespace common\components\Clinical\Home\Sections;

use common\components\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
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
