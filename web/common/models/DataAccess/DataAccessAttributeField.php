<?php

namespace common\models\DataAccess;

use common\components\Platform\Core\DataAccess\Attribute\DatabaseAttributeDefinitionSource;
use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use yii\db\ActiveRecord;

/**
 * Campo editable de un grupo de atributos DataAccess (esquema de formulario en BD).
 *
 * @property int $id
 * @property string $entity_group_key
 * @property string $field_name
 * @property string $field_type
 * @property string|null $label
 * @property string|null $config_json JSON en BD (Yii puede exponerlo como array<string, mixed> al leer columna json)
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
            [['config_json'], 'safe'],
            [['sort_order'], 'integer'],
            [['active'], 'integer'],
            [['active'], 'default', 'value' => 1],
            [
                ['entity_group_key', 'field_name'],
                'unique',
                'targetAttribute' => ['entity_group_key', 'field_name'],
            ],
            [['entity_group_key'], 'validateEntityGroupKey'],
            [['field_type'], 'validateFieldType'],
            [['config_json'], 'validateConfigJson'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fieldTypeOptions(): array
    {
        return [
            'text' => 'Texto',
            'date' => 'Fecha',
            'enum' => 'Selección (enum → select en UI)',
            'hidden' => 'Oculto',
            'custom_widget' => 'Widget personalizado',
        ];
    }

    public function validateEntityGroupKey(string $attribute): void
    {
        $key = trim((string) $this->$attribute);
        if ($key === '') {
            return;
        }
        $catalog = new AttributeGroupCatalog();
        if (!$catalog->entityGroupExists($key)) {
            $this->addError($attribute, 'Grupo no registrado en data-access-config.');
        }
    }

    public function validateFieldType(string $attribute): void
    {
        $type = trim((string) $this->$attribute);
        if ($type === '') {
            return;
        }
        if (!isset(self::fieldTypeOptions()[$type])) {
            $this->addError($attribute, 'Tipo de campo no soportado.');
        }
    }

    public function validateConfigJson(string $attribute): void
    {
        $raw = $this->$attribute;
        if ($raw === null || $raw === '') {
            $this->$attribute = null;

            return;
        }
        if (is_array($raw)) {
            return;
        }
        if (!is_string($raw)) {
            $this->addError($attribute, 'JSON inválido (objeto esperado).');

            return;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            $this->$attribute = null;

            return;
        }
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            $this->addError($attribute, 'JSON inválido (objeto esperado).');

            return;
        }
        $this->$attribute = $decoded;
    }

    public function configJsonForForm(): string
    {
        $config = $this->decodedConfig();

        return $config === [] ? '' : json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
        $raw = $this->config_json;
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw)) {
            return [];
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }
        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }
}
