<?php

namespace common\components\Home\Service\Sections;

use common\components\Clinical\Emergency\Service\GuardiaEfectorAccess;
use common\components\Clinical\Emergency\Service\GuardiaIndicadoresService;

final class EmergencyIndicatorsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $idEfector = GuardiaEfectorAccess::resolveIdEfector(
            isset($context['id_efector']) ? (int) $context['id_efector'] : null
        );
        GuardiaEfectorAccess::assertCanAccessEfector($idEfector);

        return (new GuardiaIndicadoresService())->resumen($idEfector);
    }
}
