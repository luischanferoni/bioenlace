<?php

namespace common\components\Clinical\Emergency\Service;

use common\models\Clinical\Encounter;
use common\models\Guardia;

/**
 * Resuelve el encounter clínico vinculado a un episodio de guardia (parent GUARDIA).
 */
final class GuardiaEncounterResolver
{
    public function findLatestForGuardia(int $guardiaId): ?Encounter
    {
        $parentType = Encounter::PARENT_CLASSES[Encounter::PARENT_GUARDIA] ?? Guardia::class;

        return Encounter::find()
            ->where([
                'parent_type' => $parentType,
                'parent_id' => $guardiaId,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }
}
