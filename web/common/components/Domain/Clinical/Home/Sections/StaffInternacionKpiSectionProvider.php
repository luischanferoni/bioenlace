<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Clinical\Inpatient\Service\InternacionIndicadoresService;
use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

final class StaffInternacionKpiSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (isset($context['id_efector'])) {
            $params['id_efector'] = (int) $context['id_efector'];
        }

        try {
            $idEfector = EfectorAccessService::assertAndResolveIdEfector('Internacion.view_map', $params);
        } catch (DomainOperationForbiddenException $e) {
            throw new \InvalidArgumentException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.', 0, $e);
        }

        $d = (new InternacionIndicadoresService())->resumen($idEfector);
        $ocupacion = $d['ocupacion_pct'];

        return [
            'title' => 'Internación',
            'items' => [
                [
                    'label' => 'Camas ocupadas',
                    'value' => (string) ($d['camas_ocupadas'] ?? 0) . ' / ' . (string) ($d['camas_total'] ?? 0),
                ],
                [
                    'label' => 'Ocupación',
                    'value' => $ocupacion !== null ? $ocupacion . '%' : '—',
                ],
                [
                    'label' => 'Internaciones activas',
                    'value' => (string) ($d['internaciones_activas'] ?? 0),
                ],
                [
                    'label' => 'Estadía media (días)',
                    'value' => $d['estadia_media_dias'] !== null ? (string) $d['estadia_media_dias'] : '—',
                ],
            ],
        ];
    }
}
