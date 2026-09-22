<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyBoardCapabilityService;
use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyQueueService;
use common\components\Domain\Organization\Efector\Application\Authorization\EfectorAccessService;
use common\components\Domain\Organization\Pes\Application\Service\ProfesionalHorarioActivaService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\models\Clinical\Encounter;
use Yii;

final class EmergencyBoardSectionProvider implements HomePanelSectionProviderInterface
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

        $idPersona = 0;
        if (Yii::$app->has('user', true)) {
            $idPersona = (int) (Yii::$app->user->getIdPersona() ?? 0);
        }
        if ($idPersona <= 0
            || !ProfesionalHorarioActivaService::personaTieneHorarioActivo(
                $idPersona,
                $idEfector,
                Encounter::ENCOUNTER_CLASS_EMER
            )
        ) {
            $proxima = $idPersona > 0
                ? ProfesionalHorarioActivaService::proximoHorarioInicio(
                    $idPersona,
                    $idEfector,
                    Encounter::ENCOUNTER_CLASS_EMER
                )
                : null;

            $caps = new EmergencyBoardCapabilityService();

            return [
                'items' => [],
                'requires_cobertura' => true,
                'puede_triage' => $caps->canTriage(),
                'puede_ingresar' => $caps->canIngresar(),
                'puede_ingresar_dni' => $caps->canIngresarConDni(),
                'puede_atender' => $caps->canAtender(),
                'puede_documentar' => $caps->canDocumentar(),
                'puede_retiro' => $caps->canRetiroEnTablero(),
                'empty_message' => ProfesionalHorarioActivaService::mensajeSinHorarioParaSesion(
                    Encounter::ENCOUNTER_CLASS_EMER,
                    ['proxima_inicio' => $proxima]
                ),
            ];
        }

        $tablero = (new EmergencyQueueService())->tablero($idEfector, ['solo_activos' => true]);
        $caps = new EmergencyBoardCapabilityService();

        return [
            'items' => is_array($tablero['items'] ?? null) ? $tablero['items'] : [],
            'puede_triage' => $caps->canTriage(),
            'puede_ingresar' => $caps->canIngresar(),
            'puede_ingresar_dni' => $caps->canIngresarConDni(),
            'puede_atender' => $caps->canAtender(),
            'puede_documentar' => $caps->canDocumentar(),
            'puede_retiro' => $caps->canRetiroEnTablero(),
        ];
    }
}
