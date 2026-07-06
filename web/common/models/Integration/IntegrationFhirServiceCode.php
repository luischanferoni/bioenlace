<?php

namespace common\models\Integration;

use common\models\Efector;
use common\models\Servicio;
use yii\db\ActiveRecord;

/**
 * Catálogo: código HealthcareService FHIR → servicio Bioenlace.
 *
 * @property int $id
 * @property string $source_system
 * @property string $code_system
 * @property string $code_value
 * @property int $id_servicio
 * @property int $id_efector_scope 0 = global
 * @property string|null $label
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
class IntegrationFhirServiceCode extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public const SCOPE_GLOBAL = 0;

    public static function tableName(): string
    {
        return 'integration_fhir_service_code';
    }

    public function rules(): array
    {
        return [
            [['source_system', 'code_system', 'code_value', 'id_servicio'], 'required'],
            [['id_servicio', 'id_efector_scope'], 'integer'],
            [['source_system'], 'string', 'max' => 64],
            [['code_system'], 'string', 'max' => 256],
            [['code_value'], 'string', 'max' => 64],
            [['label'], 'string', 'max' => 255],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['id_efector_scope'], 'default', 'value' => self::SCOPE_GLOBAL],
        ];
    }

    public function getServicio()
    {
        return $this->hasOne(Servicio::class, ['id_servicio' => 'id_servicio']);
    }

    public function getEfectorScope()
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'id_efector_scope']);
    }
}
