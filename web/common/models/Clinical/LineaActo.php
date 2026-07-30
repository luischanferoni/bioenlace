<?php

namespace common\models\Clinical;

use common\models\Servicio;
use yii\db\ActiveRecord;

/**
 * Puente N:M línea asistencial (`servicios`) ↔ acto clínico.
 *
 * @property int $id
 * @property int $id_servicio
 * @property int $id_acto
 * @property int|null $id_efector
 * @property bool $preferente
 */
class LineaActo extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%linea_acto}}';
    }

    public function rules(): array
    {
        return [
            [['id_servicio', 'id_acto'], 'required'],
            [['id_servicio', 'id_acto', 'id_efector'], 'integer'],
            [['preferente'], 'boolean'],
            [['preferente'], 'default', 'value' => false],
        ];
    }

    public function getServicio(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Servicio::class, ['id_servicio' => 'id_servicio']);
    }

    public function getActo(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ActoClinico::class, ['id' => 'id_acto']);
    }
}
