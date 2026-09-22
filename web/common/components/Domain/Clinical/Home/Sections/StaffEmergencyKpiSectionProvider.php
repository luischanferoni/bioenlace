<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyIndicatorsService;
use common\components\Domain\Organization\Efector\Application\Authorization\EfectorOperationAccess;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

final class StaffEmergencyKpiSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (isset($context['id_efector'])) {
            $params['id_efector'] = (int) $context['id_efector'];
        }

        try {
            $idEfector = EfectorOperationAccess::assertAndResolveIdEfector('GuardiaEpisode.view_board', $params);
        } catch (DomainOperationForbiddenException $e) {
            throw new \InvalidArgumentException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.', 0, $e);
        }

        $d = (new EmergencyIndicatorsService())->resumen($idEfector);

        return [
            'items' => [
                [
                    'label' => 'Activos',
                    'value' => (string) ($d['activos'] ?? 0),
                ],
                [
                    'label' => 'Sin triage',
                    'value' => (string) ($d['sin_triage'] ?? 0),
                ],
                [
                    'label' => 'Ingresos hoy',
                    'value' => (string) ($d['ingresos_hoy'] ?? 0),
                ],
            ],
        ];
    }
}
