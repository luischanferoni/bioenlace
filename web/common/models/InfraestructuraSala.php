<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "infraestructura_sala".
 *
 * @property int $id
 * @property int|null $nro_sala
 * @property string|null $descripcion
 * @property int|null $covid
 * @property int|null $id_responsable
 * @property int $id_piso
 * @property int|null $id_servicio
 * @property string|null $tipo_sala
 *
 * @property InfraestructuraCama[] $infraestructuraCamas
 * @property InfraestructuraPiso $piso
 */
class InfraestructuraSala extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'infraestructura_sala';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_piso'], 'required'],
            [['id', 'nro_sala', 'covid', 'id_responsable', 'id_piso', 'id_servicio'], 'integer'],
            [['tipo_sala'], 'string'],
            [['descripcion'], 'string', 'max' => 255],
            [['id'], 'unique'],
            [['id_piso'], 'exist', 'skipOnError' => true, 'targetClass' => InfraestructuraPiso::className(), 'targetAttribute' => ['id_piso' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nro_sala' => 'Nro Sala',
            'descripcion' => 'Descripcion',
            'covid' => 'Covid',
            'id_responsable' => 'Responsable',
            'id_piso' => 'Piso',
            'id_servicio' => 'Servicio',
            'tipo_sala' => 'Tipo Sala',
        ];
    }

    /**
     * Gets query for [[InfraestructuraCamas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInfraestructuraCamas()
    {
        return $this->hasMany(InfraestructuraCama::className(), ['id_sala' => 'id']);
    }

    /**
     * Gets query for [[Piso]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPiso()
    {
        return $this->hasOne(InfraestructuraPiso::className(), ['id' => 'id_piso']);
    }

    /**
     * Gets query for [[Servicio]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServicio()
    {
        return $this->hasOne(Servicio::className(), ['id_servicio' => 'id_servicio']);
    } 

    /**
     * Gets query for [[Responsable]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getResponsable()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_responsable']);
    }         
}
