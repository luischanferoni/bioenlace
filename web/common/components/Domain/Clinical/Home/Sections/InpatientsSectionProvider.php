<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Clinical\Home\StaffClinicalDayListService;
use common\components\Domain\Organization\Service\ProfesionalCobertura\ProfesionalCoberturaActivaService;
use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\models\Clinical\Encounter;
use Yii;

final class InpatientsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $idEfector = (int) ($context['id_efector'] ?? 0);
        if ($idEfector <= 0) {
            $idEfector = (int) (Yii::$app->user->getIdEfector() ?? 0);
        }

        $idPersona = 0;
        if (Yii::$app->has('user', true)) {
            $idPersona = (int) (Yii::$app->user->getIdPersona() ?? 0);
        }

        if (AgendaByEncounterClassMetadata::impViewRequiresCobertura()
            && ($idPersona <= 0
                || $idEfector <= 0
                || !ProfesionalCoberturaActivaService::personaTieneCoberturaActiva(
                    $idPersona,
                    $idEfector,
                    Encounter::ENCOUNTER_CLASS_IMP
                ))
        ) {
            $proxima = ($idPersona > 0 && $idEfector > 0)
                ? ProfesionalCoberturaActivaService::proximaCoberturaInicio(
                    $idPersona,
                    $idEfector,
                    Encounter::ENCOUNTER_CLASS_IMP
                )
                : null;

            return [
                'items' => [],
                'total' => 0,
                'requires_cobertura' => true,
                'empty_message' => ProfesionalCoberturaActivaService::mensajeSinCoberturaParaSesion(
                    Encounter::ENCOUNTER_CLASS_IMP,
                    ['proxima_inicio' => $proxima]
                ),
            ];
        }

        $items = (new StaffClinicalDayListService())->internadosPorEfector();

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }
}
