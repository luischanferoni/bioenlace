<?php

namespace common\models\Emergency;

use yii\db\ActiveRecord;

/**
 * SLA operativo de guardia por efector.
 *
 * @property int $id_efector
 * @property int $minutos_espera_triage
 * @property int $minutos_espera_medico_1
 * @property int $minutos_espera_medico_2
 * @property int $minutos_espera_medico_3
 * @property int $minutos_espera_medico_4
 * @property int $minutos_espera_medico_5
 * @property string|null $updated_at
 */
class EfectorEmergencyConfig extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%efector_emergency_config}}';
    }

    public function rules(): array
    {
        return [
            [['id_efector'], 'required'],
            [
                [
                    'minutos_espera_triage',
                    'minutos_espera_medico_1',
                    'minutos_espera_medico_2',
                    'minutos_espera_medico_3',
                    'minutos_espera_medico_4',
                    'minutos_espera_medico_5',
                ],
                'integer',
                'min' => 0,
            ],
        ];
    }

    public static function forEfector(int $idEfector): self
    {
        $row = static::findOne($idEfector);
        if ($row !== null) {
            return $row;
        }

        $row = new self();
        $row->id_efector = $idEfector;
        $row->minutos_espera_triage = 15;
        $row->minutos_espera_medico_1 = 0;
        $row->minutos_espera_medico_2 = 10;
        $row->minutos_espera_medico_3 = 60;
        $row->minutos_espera_medico_4 = 120;
        $row->minutos_espera_medico_5 = 240;

        return $row;
    }

    public function minutosEsperaMedicoPorNivel(int $nivel): int
    {
        $map = [
            1 => (int) $this->minutos_espera_medico_1,
            2 => (int) $this->minutos_espera_medico_2,
            3 => (int) $this->minutos_espera_medico_3,
            4 => (int) $this->minutos_espera_medico_4,
            5 => (int) $this->minutos_espera_medico_5,
        ];

        return $map[$nivel] ?? (int) $this->minutos_espera_medico_3;
    }
}
