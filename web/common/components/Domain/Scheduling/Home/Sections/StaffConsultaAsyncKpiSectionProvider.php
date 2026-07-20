<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Domain\Scheduling\Service\ConsultaAsyncIndicadoresCatalogService;
use common\components\Domain\Scheduling\Service\ConsultaAsyncIndicadoresService;
use common\components\Domain\Scheduling\Service\ConsultaAsyncStaffScopeService;
use common\components\Domain\Scheduling\Service\ServicioTeleconsultaPoliticaService;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use Yii;

/**
 * KPIs agregados de consultas async (staff / AdminEfector en el efector).
 */
final class StaffConsultaAsyncKpiSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $catalog = new ConsultaAsyncIndicadoresCatalogService();
        $idEfector = (int) ($context['id_efector'] ?? Yii::$app->user->getIdEfector());
        if ($idEfector <= 0) {
            return ['title' => '', 'items' => []];
        }

        $indicadores = new ConsultaAsyncIndicadoresService();
        $efectorCompleto = ServicioTeleconsultaPoliticaService::usuarioEsAdminEfectorOperativo();
        $serviceIds = $efectorCompleto
            ? $indicadores->idServiciosEnEfector($idEfector)
            : (new ConsultaAsyncStaffScopeService())->idServiciosAtendiblesEnEfector();

        if ($serviceIds === []) {
            return ['title' => '', 'items' => []];
        }

        $d = $indicadores->resumen($serviceIds, $idEfector);
        $mediana = $d['mediana_respuesta_min'] ?? null;

        return [
            'title' => $catalog->tituloSeccion() !== '' ? $catalog->tituloSeccion() : 'Consultas por mensaje',
            'items' => [
                [
                    'label' => $catalog->label('pendientes', 'Pendientes'),
                    'value' => (string) ($d['pendientes'] ?? 0),
                ],
                [
                    'label' => $catalog->label('sla_incumplidos', 'SLA vencidos'),
                    'value' => (string) ($d['sla_incumplidos'] ?? 0),
                ],
                [
                    'label' => $catalog->label('cerradas_periodo', 'Cerradas'),
                    'value' => (string) ($d['cerradas_periodo'] ?? 0),
                ],
                [
                    'label' => $catalog->label('tasa_resolucion', 'Tasa resolución'),
                    'value' => (string) ($d['tasa_resolucion'] ?? '—'),
                ],
                [
                    'label' => $catalog->label('cumplimiento_sla', 'Cumplimiento SLA'),
                    'value' => (string) ($d['cumplimiento_sla_pct'] ?? '—'),
                ],
                [
                    'label' => $catalog->label('mediana_respuesta', 'Mediana 1.ª respuesta'),
                    'value' => $mediana !== null ? $mediana . ' min' : '—',
                ],
            ],
        ];
    }
}
