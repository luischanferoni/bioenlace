<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\models\Clinical\Encounter;
use common\models\Guardia;

/**
 * Resuelve el encounter clínico vinculado a un episodio de guardia (parent GUARDIA).
 */
final class GuardiaEncounterResolver
{
    public function findLatestForGuardia(int $guardiaId): ?Encounter
    {
        $types = [
            Encounter::PARENT_GUARDIA,
            Encounter::PARENT_CLASSES[Encounter::PARENT_GUARDIA] ?? Guardia::class,
            Guardia::class,
            ltrim(Guardia::class, '\\'),
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
