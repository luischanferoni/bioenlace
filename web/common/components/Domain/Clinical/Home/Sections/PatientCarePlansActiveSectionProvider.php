<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Clinical\Service\CarePlanPresentationService;
use common\components\Domain\Clinical\Service\PatientActiveCarePlanQuery;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\components\Domain\Scheduling\Service\ConsultaAsyncBandejaService;

final class PatientCarePlansActiveSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (!empty($context['subject_persona_id'])) {
            $params['subject_persona_id'] = (int) $context['subject_persona_id'];
        }
        $subjectSvc = new PersonRepresentationSubjectService();
        $idPersona = $subjectSvc->resolveAndAuthorize(
            $params,
            RepresentationPermission::CLINICAL_CARE_PLAN
        );

        $plans = (new PatientActiveCarePlanQuery())->listActive($idPersona);
        $presentation = new CarePlanPresentationService();
        $bandejaTratamiento = (new ConsultaAsyncBandejaService())->listForPaciente($idPersona, [
            'ui_group' => 'tratamiento',
        ]);
        $activasByPlan = $this->indexByCarePlanId($bandejaTratamiento['items'] ?? []);
        $historialByPlan = $this->indexByCarePlanId($bandejaTratamiento['history']['items'] ?? []);

        $data = [];
        foreach ($plans as $plan) {
            $summary = $presentation->toPatientSummary($plan, true);
            $planId = (int) ($summary['id'] ?? $plan->id ?? 0);
            $activas = $activasByPlan[$planId] ?? [];
            $historial = $historialByPlan[$planId] ?? [];
            $summary['solicitudes_activas'] = $activas;
            $summary['solicitudes_historial'] = $historial;
            $summary['solicitudes_pendientes_count'] = count($activas);
            $data[] = $summary;
        }

        return [
            'items' => $data,
            'total' => count($data),
        ];
    }

    /**
     * @param list<mixed> $items
     * @return array<int, list<array<string, mixed>>>
     */
    private function indexByCarePlanId(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $planId = (int) ($item['care_plan_id'] ?? 0);
            if ($planId <= 0) {
                continue;
            }
            $out[$planId][] = $item;
        }

        return $out;
    }
}
