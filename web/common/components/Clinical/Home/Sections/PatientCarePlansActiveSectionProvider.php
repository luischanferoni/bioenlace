<?php

namespace common\components\Clinical\Home\Sections;

use common\components\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Clinical\Service\CarePlanPresentationService;
use common\components\Clinical\Service\PatientActiveCarePlanQuery;
use common\components\Person\Representation\Enum\RepresentationPermission;
use common\components\Person\Representation\Service\PersonRepresentationSubjectService;

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
        $data = [];
        foreach ($plans as $plan) {
            $data[] = $presentation->toPatientSummary($plan, true);
        }

        return [
            'items' => $data,
            'total' => count($data),
        ];
    }
}
