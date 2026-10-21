<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\ListEncounterAsyncInbox;
use Yii;

/**
 * Sección home: solo solicitudes async generales (sin ancla de tratamiento ni condición).
 * Las de tratamiento/condición van anidadas en care_plans_active / conditions_active.
 */
final class PatientEncounterAsyncSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $idPersona = (int) ($context['subject_persona_id'] ?? Yii::$app->user->getIdPersona());

        return (new ListEncounterAsyncInbox())->listForPaciente($idPersona, [
            'ui_group' => 'consultas',
        ]);
    }
}
