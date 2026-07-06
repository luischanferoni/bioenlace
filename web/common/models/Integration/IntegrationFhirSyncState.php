<?php

namespace common\models\Integration;

use yii\db\ActiveRecord;

/**
 * Cursor de sincronización pull FHIR por conector.
 *
 * @property string $source_system
 * @property string|null $last_success_at
 * @property string|null $last_cursor
 * @property string|null $last_error
 * @property string $updated_at
 */
class IntegrationFhirSyncState extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'integration_fhir_sync_state';
    }

    public static function primaryKey(): array
    {
        return ['source_system'];
    }

    public function rules(): array
    {
        return [
            [['source_system', 'updated_at'], 'required'],
            [['last_success_at', 'last_cursor', 'updated_at'], 'safe'],
            [['last_error'], 'string'],
            [['source_system'], 'string', 'max' => 64],
            [['last_cursor'], 'string', 'max' => 64],
        ];
    }

    public static function getOrCreate(string $sourceSystem): self
    {
        $row = static::findOne(['source_system' => $sourceSystem]);
        if ($row !== null) {
            return $row;
        }

        $row = new self();
        $row->source_system = $sourceSystem;
        $row->updated_at = gmdate('Y-m-d H:i:s');

        return $row;
    }
}
