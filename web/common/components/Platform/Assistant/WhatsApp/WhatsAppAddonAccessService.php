<?php

namespace common\components\Platform\Assistant\WhatsApp;

use common\components\Domain\Organization\Efector\Application\Service\EfectorEncounterEntitlementService;
use common\models\Clinical\Persona_hc;
use common\models\Scheduling\Turno;

/**
 * Resuelve si el add-on WhatsApp está contratado para los efectores del paciente.
 *
 * El paciente puede tener más de un efector: alcanza con que uno tenga el add-on.
 */
final class WhatsAppAddonAccessService
{
    /**
     * @return list<int>
     */
    public static function personaEfectorIds(int $idPersona): array
    {
        if ($idPersona <= 0) {
            return [];
        }

        $ids = Turno::find()
            ->select('id_efector')
            ->where(['id_persona' => $idPersona])
            ->andWhere(['not', ['id_efector' => null]])
            ->distinct()
            ->column();

        $hcIds = Persona_hc::find()
            ->select('id_efector')
            ->where(['id_persona' => $idPersona])
            ->andWhere(['not', ['id_efector' => null]])
            ->distinct()
            ->column();

        return array_values(array_unique(array_map(
            'intval',
            array_merge(is_array($ids) ? $ids : [], is_array($hcIds) ? $hcIds : [])
        )));
    }

    public static function allowsPersona(int $idPersona): bool
    {
        foreach (self::personaEfectorIds($idPersona) as $idEfector) {
            if (EfectorEncounterEntitlementService::isWhatsappAllowedForEfector($idEfector)) {
                return true;
            }
        }

        return false;
    }

    public static function allowsEfector(int $idEfector): bool
    {
        return EfectorEncounterEntitlementService::isWhatsappAllowedForEfector($idEfector);
    }
}
