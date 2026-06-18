<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Domain\Scheduling\Service\ServicioTeleconsultaPoliticaCatalogService;
use common\components\Domain\Scheduling\Service\ServicioTeleconsultaPoliticaService;
use common\components\Domain\Scheduling\Service\StaffModalidadInsightMetricsService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

/**
 * KPI agregado del efector (AdminEfector): potencial remoto y servicios con videollamada.
 */
final class StaffEfectorModalidadKpiSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        if (!ServicioTeleconsultaPoliticaService::usuarioEsAdminEfectorOperativo()) {
            return ['title' => '', 'items' => []];
        }

        $params = [];
        if (isset($context['id_efector'])) {
            $params['id_efector'] = (int) $context['id_efector'];
        }

        try {
            $idEfector = EfectorAccessService::assertAndResolveIdEfector(
                'servicio-teleconsulta.configurar-efector-flow',
                $params
            );
        } catch (DomainOperationForbiddenException $e) {
            return ['title' => '', 'items' => []];
        }

        $catalog = new ServicioTeleconsultaPoliticaCatalogService();
        $kpiLabels = $catalog->kpiEfector();
        $fechaHasta = isset($context['fecha']) ? (string) $context['fecha'] : date('Y-m-d');

        $insight = (new StaffModalidadInsightMetricsService())->resumen([
            'id_efector' => $idEfector,
            'fecha_hasta' => $fechaHasta,
        ]);

        $resumen = (new ServicioTeleconsultaPoliticaService())->resumenEfector($idEfector);

        $items = [];
        $sugerido = (int) ($insight['presencial_insight_sugerido'] ?? 0);
        if ($sugerido > 0) {
            $value = (string) $sugerido;
            $pct = $insight['pct_sugerido'] ?? null;
            if ($pct !== null) {
                $value .= ' (' . $pct . '%)';
            }
            $items[] = [
                'label' => $kpiLabels['label_presencial_remoto'],
                'value' => $value,
            ];
        }

        $items[] = [
            'label' => $kpiLabels['label_servicios_con_video'],
            'value' => (int) ($resumen['servicios_con_teleconsulta'] ?? 0)
                . ' / ' . (int) ($resumen['servicios_total'] ?? 0),
        ];

        return [
            'title' => $kpiLabels['title'] !== '' ? $kpiLabels['title'] : 'Atención remota (efector)',
            'items' => $items,
        ];
    }
}
