<?php

namespace common\models\Clinical;

use common\models\Servicio;
use yii\db\ActiveRecord;

/**
 * Acto clínico codificado (SNOMED / LOINC / FHIR) — ServiceRequest.code.
 *
 * @property int $id
 * @property string $code
 * @property string $code_system
 * @property string $display
 * @property string $fhir_category
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ActoClinico extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%actos_clinicos}}';
    }

    public function rules(): array
    {
        return [
            [['code', 'code_system', 'display'], 'required'],
            [['code'], 'string', 'max' => 64],
            [['code_system'], 'string', 'max' => 128],
            [['display'], 'string', 'max' => 512],
            [['fhir_category'], 'string', 'max' => 64],
            [['created_at', 'updated_at'], 'safe'],
            [['code_system', 'code'], 'unique', 'targetAttribute' => ['code_system', 'code']],
        ];
    }

    public function getLineaActos(): \yii\db\ActiveQuery
    {
        return $this->hasMany(LineaActo::class, ['id_acto' => 'id']);
    }
}
