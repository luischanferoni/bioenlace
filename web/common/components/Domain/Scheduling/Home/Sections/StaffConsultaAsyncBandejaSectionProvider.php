<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\ListConsultaAsyncInbox;

final class StaffConsultaAsyncBandejaSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        return (new ListConsultaAsyncInbox())->listForStaffBandeja();
    }
}
