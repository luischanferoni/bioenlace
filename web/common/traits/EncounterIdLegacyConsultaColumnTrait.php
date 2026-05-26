<?php

namespace common\traits;

use common\models\Clinical\Encounter;

/**
 * Columna `encounter_id` (o legacy `id_consulta` pre-migración) almacena el id de {@see Encounter}.
 */
trait EncounterIdLegacyConsultaColumnTrait
{
    public static function encounterFkAttribute(): string
    {
        $schema = static::getTableSchema();
        if ($schema !== null && isset($schema->columns['encounter_id'])) {
            return 'encounter_id';
        }

        return 'id_consulta';
    }

    public function getEncounter_id(): ?int
    {
        $col = static::encounterFkAttribute();
        $id = $this->getAttribute($col);

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public function setEncounter_id($value): void
    {
        $this->setAttribute(
            static::encounterFkAttribute(),
            $value !== null && $value !== '' ? (int) $value : null
        );
    }

    /** @deprecated Alias de {@see getEncounter_id()} */
    public function getId_consulta(): ?int
    {
        return $this->getEncounter_id();
    }

    /** @deprecated Alias de {@see setEncounter_id()} */
    public function setId_consulta($value): void
    {
        $this->setEncounter_id($value);
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        $col = static::encounterFkAttribute();

        return $this->hasOne(Encounter::class, ['id' => $col]);
    }

    /**
     * @return array<string, int>
     */
    protected static function encounterIdQueryCondition(int $encounterId): array
    {
        return [static::encounterFkAttribute() => $encounterId];
    }
}
