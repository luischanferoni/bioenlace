<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Clinical\Emergency\Service\GuardiaIndicadoresService;
use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

final class StaffGuardiaKpiSectionProvider implements HomePanelSectionProviderInterface
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

        $d = (new GuardiaIndicadoresService())->resumen($idEfector);
        $tiempos = is_array($d['tiempos_hoy'] ?? null) ? $d['tiempos_hoy'] : [];
        $minMedico = $tiempos['minutos_a_medico'] ?? null;

        return [
            'title' => 'Guardia',
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
                [
                    'label' => 'SLA incumplidos',
                    'value' => (string) ($d['sla_incumplidos_tablero'] ?? 0),
                ],
                [
                    'label' => 'Mediana a médico (hoy)',
                    'value' => $minMedico !== null ? $minMedico . ' min' : '—',
                ],
            ],
        ];
    }
}
