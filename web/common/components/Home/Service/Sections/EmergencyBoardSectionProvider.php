<?php

namespace common\components\Home\Service\Sections;

use common\components\Clinical\Emergency\Service\GuardiaEfectorAccess;
use common\components\Clinical\Emergency\Service\GuardiaQueueService;

final class EmergencyBoardSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $idEfector = GuardiaEfectorAccess::resolveIdEfector(
            isset($context['id_efector']) ? (int) $context['id_efector'] : null
        );
        GuardiaEfectorAccess::assertCanAccessEfector($idEfector);

        return (new GuardiaQueueService())->tablero($idEfector, ['solo_activos' => true]);
    }
}
