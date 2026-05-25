<?php

namespace common\models\Emergency;

use common\models\Guardia;
use common\models\ProfesionalEfectorServicio;
use yii\db\ActiveRecord;

/**
 * Triage estructurado (1:1 con guardia activa).
 *
 * @property int $id
 * @property int $guardia_id
 * @property string $scale
 * @property int $level
 * @property string|null $reason_code
 * @property string $reason_text
 * @property string|null $vitals_json
 * @property string $triaged_at
 * @property int|null $id_profesional_efector_servicio
 * @property string $created_at
 * @property string $updated_at
 */
class GuardiaTriage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%guardia_triage}}';
    }

    public function rules(): array
    {
        return [
            [['guardia_id', 'level', 'reason_text', 'triaged_at'], 'required'],
            [['guardia_id', 'level', 'id_profesional_efector_servicio'], 'integer'],
            [['level'], 'integer', 'min' => 1, 'max' => 5],
            [['scale'], 'string', 'max' => 32],
            [['reason_code'], 'string', 'max' => 64],
            [['reason_text'], 'string', 'max' => 500],
            [['vitals_json', 'triaged_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getGuardia()
    {
        return $this->hasOne(Guardia::class, ['id' => 'guardia_id']);
    }

    public function getProfesionalEfectorServicio()
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getVitalsArray(): ?array
    {
        if ($this->vitals_json === null || $this->vitals_json === '') {
            return null;
        }
        $decoded = json_decode($this->vitals_json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
