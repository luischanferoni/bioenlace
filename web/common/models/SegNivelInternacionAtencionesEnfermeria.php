<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "seg_nivel_internacion_atenciones_enfermeria".
 *
 * @property int $id
 * @property string $datos
 * @property string $observaciones
 * @property int $id_user
 * @property string $fecha
 * @property string $hora
 * @property string $created_at
 */
class SegNivelInternacionAtencionesEnfermeria extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_atenciones_enfermeria';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['datos', 'id_user', 'fecha', 'id_internacion'], 'required'],
            [['observaciones'], 'string'],
            [[ 'id_user'], 'integer'],
            [['fecha', 'hora', 'created_at'], 'safe'],
            [['datos'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'datos' => 'Datos',
            'observaciones' => 'Observaciones',
            'id_user' => 'Id User',
            'fecha' => 'Fecha',
            'hora' => 'Hora'
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(Persona::className(), ['id_user' => 'id_user']);
    }
}
