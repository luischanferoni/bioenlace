<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Clinical\Emergency\Service\GuardiaQueueService;
use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Domain\Organization\Service\ProfesionalCobertura\ProfesionalCoberturaActivaService;
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
            || !ProfesionalCoberturaActivaService::personaTieneCoberturaActiva(
                $idPersona,
                $idEfector,
                Encounter::ENCOUNTER_CLASS_EMER
            )
        ) {
            $proxima = $idPersona > 0
                ? ProfesionalCoberturaActivaService::proximaCoberturaInicio(
                    $idPersona,
                    $idEfector,
                    Encounter::ENCOUNTER_CLASS_EMER
                )
                : null;

            return [
                'items' => [],
                'requires_cobertura' => true,
                'empty_message' => ProfesionalCoberturaActivaService::mensajeSinCoberturaParaSesion(
                    Encounter::ENCOUNTER_CLASS_EMER,
                    ['proxima_inicio' => $proxima]
                ),
            ];
        }

        $tablero = (new GuardiaQueueService())->tablero($idEfector, ['solo_activos' => true]);

        return [
            'items' => is_array($tablero['items'] ?? null) ? $tablero['items'] : [],
        ];
    }
}
