<?php

namespace common\models\DataAccess;

use common\components\Core\DataAccess\Attribute\DatabaseAttributeDefinitionSource;
use yii\db\ActiveRecord;

/**
 * Campo editable de un grupo de atributos DataAccess (esquema de formulario en BD).
 *
 * @property int $id
 * @property string $entity_group_key
 * @property string $field_name
 * @property string $field_type
 * @property string|null $label
 * @property string|null $config_json
 * @property int $sort_order
 * @property int $active
 */
class DataAccessAttributeField extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'data_access_attribute_field';
    }

    public function rules(): array
    {
        return [
            [['entity_group_key', 'field_name', 'field_type'], 'required'],
            [['entity_group_key'], 'string', 'max' => 128],
            [['field_name'], 'string', 'max' => 64],
            [['field_type'], 'string', 'max' => 32],
            [['label'], 'string', 'max' => 255],
            [['config_json'], 'string'],
            [['sort_order'], 'integer'],
            [['active'], 'integer'],
            [['active'], 'default', 'value' => 1],
            [
                ['entity_group_key', 'field_name'],
                'unique',
                'targetAttribute' => ['entity_group_key', 'field_name'],
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'entity_group_key' => 'Grupo',
            'field_name' => 'Campo',
            'field_type' => 'Tipo',
            'label' => 'Etiqueta',
            'config_json' => 'Configuración',
            'sort_order' => 'Orden',
            'active' => 'Activo',
        ];
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        DatabaseAttributeDefinitionSource::clearCache();
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        DatabaseAttributeDefinitionSource::clearCache();
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalogDefinition(): array
    {
        $def = [
            'type' => trim((string) $this->field_type) ?: 'text',
        ];
        $label = trim((string) $this->label);
        if ($label !== '') {
            $def['label'] = $label;
        }
        $config = $this->decodedConfig();
        if ($config !== []) {
            $def = array_merge($def, $config);
        }

        return $def;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodedConfig(): array
    {
        $raw = trim((string) $this->config_json);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
