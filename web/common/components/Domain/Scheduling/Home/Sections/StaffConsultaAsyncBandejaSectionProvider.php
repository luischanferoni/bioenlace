<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Scheduling\Service\ConsultaAsyncBandejaService;

final class StaffConsultaAsyncBandejaSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        return (new ConsultaAsyncBandejaService())->listForStaffBandeja();
    }
}
