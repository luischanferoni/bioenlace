<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Domain\Scheduling\Service\AgendaAtencionRemotaCatalogService;
use common\components\Domain\Scheduling\Service\StaffModalidadInsightMetricsService;
use common\components\Domain\Scheduling\Service\TurnoAgendaMetricsService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

final class StaffAgendaKpiSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (isset($context['id_efector'])) {
            $params['id_efector'] = (int) $context['id_efector'];
        }

        try {
            $idEfector = EfectorAccessService::assertAndResolveIdEfector('turnos.indicadores-agenda-flow', $params);
        } catch (DomainOperationForbiddenException $e) {
            throw new \InvalidArgumentException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.', 0, $e);
        }

        $fechaHasta = isset($context['fecha']) ? (string) $context['fecha'] : date('Y-m-d');
        $resumen = (new TurnoAgendaMetricsService())->resumen([
            'id_efector' => $idEfector,
            'fecha_hasta' => $fechaHasta,
            'id_profesional_efector_servicio' => $this->resolvePesIdParaKpi(),
        ]);

        $insightResumen = (new StaffModalidadInsightMetricsService())->resumen([
            'id_efector' => $idEfector,
            'fecha_hasta' => $fechaHasta,
            'id_profesional_efector_servicio' => $this->resolvePesIdParaKpi(),
        ]);

        $noShowRate = $resumen['no_show_rate_pct'];
        $leadMediana = $resumen['dias_hasta_cita_mediana'];

        $items = [
            [
                'label' => 'Turnos (30 días)',
                'value' => (string) ($resumen['turnos_total'] ?? 0),
            ],
            [
                'label' => 'No-show',
                'value' => $noShowRate !== null ? $noShowRate . '%' : '—',
            ],
            [
                'label' => 'Atendidos',
                'value' => (string) ($resumen['atendidos'] ?? 0),
            ],
            [
                'label' => 'Días hasta cita (mediana)',
                'value' => $leadMediana !== null ? (string) $leadMediana : '—',
            ],
        ];

        $kpiLabel = (new AgendaAtencionRemotaCatalogService())->kpiPresencialRemoto()['label'];
        $sugerido = (int) ($insightResumen['presencial_insight_sugerido'] ?? 0);
        if ($sugerido > 0) {
            $pct = $insightResumen['pct_sugerido'];
            $value = (string) $sugerido;
            if ($pct !== null) {
                $value .= ' (' . $pct . '% con triage)';
            }
            $items[] = [
                'label' => $kpiLabel,
                'value' => $value,
            ];
        }

        return [
            'title' => 'Agenda',
            'items' => $items,
        ];
    }

    private function resolvePesIdParaKpi(): int
    {
        $pes = \Yii::$app->user->getIdProfesionalEfectorServicio();

        return $pes !== null && $pes !== '' ? (int) $pes : 0;
    }
}
