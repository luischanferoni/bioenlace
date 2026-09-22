<?php

namespace common\components\Domain\Clinical\Emergency\Application\Service;

use common\models\Clinical\Encounter;
use common\models\Clinical\Emergency\EmergencyEpisode;

/**
 * Resuelve el encounter clínico vinculado a un episodio de guardia (parent GUARDIA).
 */
final class EmergencyEncounterResolver
{
    public function findLatestForGuardia(int $guardiaId): ?Encounter
    {
        $types = [
            Encounter::PARENT_GUARDIA,
            Encounter::PARENT_CLASSES[Encounter::PARENT_GUARDIA] ?? EmergencyEpisode::class,
            EmergencyEpisode::class,
            ltrim(EmergencyEpisode::class, '\\'),
        ];
        $types = array_values(array_unique(array_filter($types)));

        return Encounter::find()
            ->where([
                'parent_type' => $types,
                'parent_id' => $guardiaId,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }
}
