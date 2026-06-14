<?php

namespace common\components\Scheduling\Home\Sections;

use common\components\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Scheduling\Service\TurnoPacienteListadoService;

final class PatientUpcomingAppointmentsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (!empty($context['subject_persona_id'])) {
            $params['subject_persona_id'] = (int) $context['subject_persona_id'];
        }

        return (new TurnoPacienteListadoService())->listForHomePanel($params);
    }
}
