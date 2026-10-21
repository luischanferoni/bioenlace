<?php

namespace common\traits;

use common\models\Clinical\Encounter;

trait EncounterIdTrait
{
    public function getEncounter_id(): ?int
    {
        $id = $this->getAttribute('encounter_id');

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public function setEncounter_id($value): void
    {
        $this->setAttribute(
            'encounter_id',
            $value !== null && $value !== '' ? (int) $value : null
        );
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    /**
     * @return array<string, int>
     */
    protected static function encounterIdQueryCondition(int $encounterId): array
    {
        return ['encounter_id' => $encounterId];
    }
}
