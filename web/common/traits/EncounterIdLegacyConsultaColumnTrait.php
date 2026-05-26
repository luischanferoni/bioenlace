<?php

namespace common\traits;

use common\models\Clinical\Encounter;

/**
 * Mapea la columna legacy `id_consulta` al id de {@see Encounter}.
 *
 * Usar hasta migración `renameColumn id_consulta → encounter_id` en la tabla correspondiente.
 */
trait EncounterIdLegacyConsultaColumnTrait
{
    public function getEncounter_id(): ?int
    {
        $id = $this->getAttribute('id_consulta');

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public function setEncounter_id($value): void
    {
        $this->setAttribute(
            'id_consulta',
            $value !== null && $value !== '' ? (int) $value : null
        );
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'id_consulta']);
    }

    /**
     * Condición de query por encounter (columna legacy `id_consulta`).
     *
     * @return array<string, int>
     */
    protected static function encounterIdQueryCondition(int $encounterId): array
    {
        return ['id_consulta' => $encounterId];
    }
}
