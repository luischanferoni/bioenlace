<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Clinical\Emergency\Service\GuardiaIndicadoresService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;

final class EmergencyIndicatorsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (isset($context['id_efector'])) {
            $params['id_efector'] = (int) $context['id_efector'];
        }

        try {
            $idEfector = EfectorAccessService::assertAndResolveIdEfector('GuardiaEpisode.view_board', $params);
        } catch (DomainOperationForbiddenException $e) {
            throw new \InvalidArgumentException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.', 0, $e);
        }

        return (new GuardiaIndicadoresService())->resumen($idEfector);
    }
}
