<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Clinical\Emergency\Service\GuardiaIndicadoresService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaQueueService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

final class EmergencyBoardSectionProvider implements HomePanelSectionProviderInterface
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

        return (new GuardiaQueueService())->tablero($idEfector, ['solo_activos' => true]);
    }
}
