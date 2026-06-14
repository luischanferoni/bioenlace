<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Scheduling\Service\TurnoPacienteListadoService;

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
