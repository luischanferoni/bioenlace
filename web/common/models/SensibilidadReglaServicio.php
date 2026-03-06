<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Servicio al que aplica una regla de sensibilidad (generalizar/ocultar para ese servicio).
 * Si una categoría tiene regla con lista vacía = nadie restringido; si tiene N servicios aquí, solo esos ven generalizado/oculto.
 *
 * @property int $id
 * @property int $id_regla
 * @property int $id_servicio
 * @property SensibilidadRegla $regla
 * @property Servicio $servicio
 */
class SensibilidadReglaServicio extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sensibilidad_regla_servicio';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_regla', 'id_servicio'], 'required'],
            [['id_regla', 'id_servicio'], 'integer'],
            [['id_regla', 'id_servicio'], 'unique', 'targetAttribute' => ['id_regla', 'id_servicio']],
            [['id_regla'], 'exist', 'skipOnError' => true, 'targetClass' => SensibilidadRegla::class, 'targetAttribute' => ['id_regla' => 'id']],
            [['id_servicio'], 'exist', 'skipOnError' => true, 'targetClass' => Servicio::class, 'targetAttribute' => ['id_servicio' => 'id_servicio']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_regla' => 'Regla',
            'id_servicio' => 'Servicio',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRegla()
    {
        return $this->hasOne(SensibilidadRegla::class, ['id' => 'id_regla']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getServicio()
    {
        return $this->hasOne(Servicio::class, ['id_servicio' => 'id_servicio']);
    }
}
