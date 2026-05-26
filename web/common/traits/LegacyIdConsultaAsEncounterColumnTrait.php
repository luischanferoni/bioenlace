<?php

namespace common\traits;

use common\models\Clinical\Encounter;

/**
 * Columna `legacy_id_consulta` (o `id_consulta` pre-migración) almacena el id de {@see Encounter}.
 */
trait LegacyIdConsultaAsEncounterColumnTrait
{
    public static function legacyConsultaFkAttribute(): string
    {
        $schema = static::getTableSchema();
        if ($schema !== null && isset($schema->columns['legacy_id_consulta'])) {
            return 'legacy_id_consulta';
        }

        return 'id_consulta';
    }

    public function getEncounter_id(): ?int
    {
        $col = static::legacyConsultaFkAttribute();
        $id = $this->getAttribute($col);

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public function setEncounter_id($value): void
    {
        $this->setAttribute(
            static::legacyConsultaFkAttribute(),
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
        $col = static::legacyConsultaFkAttribute();

        return $this->hasOne(Encounter::class, ['id' => $col]);
    }

    /**
     * @return array<string, int>
     */
    protected static function encounterIdQueryCondition(int $encounterId): array
    {
        return [static::legacyConsultaFkAttribute() => $encounterId];
    }
}
