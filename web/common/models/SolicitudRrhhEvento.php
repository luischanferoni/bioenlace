<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $id_solicitud
 * @property int|null $id_user
 * @property string $tipo
 * @property string|null $detalle
 * @property string $created_at
 */
class SolicitudRrhhEvento extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%solicitud_rrhh_evento}}';
    }

    public function rules()
    {
        return [
            [['id_solicitud', 'tipo'], 'required'],
            [['id_solicitud', 'id_user'], 'integer'],
            [['detalle'], 'string'],
            [['tipo'], 'string', 'max' => 32],
        ];
    }
}
