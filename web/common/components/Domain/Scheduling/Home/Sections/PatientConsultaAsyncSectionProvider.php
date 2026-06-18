<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Scheduling\Service\ConsultaAsyncBandejaService;
use Yii;

final class PatientConsultaAsyncSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $idPersona = (int) ($context['subject_persona_id'] ?? Yii::$app->user->getIdPersona());

        return (new ConsultaAsyncBandejaService())->listForPaciente($idPersona);
    }
}
