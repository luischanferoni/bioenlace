<?php

namespace frontend\modules\mapear\models;

use Yii;

/**
 * This is the model class for table "regla".
 *
 * @property int $id
 * @property string $tipo
 * @property int $id_destino
 */
class Regla extends MapearActiveRecord
{
    public $cumple;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'regla';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tipo', 'id_destino'], 'required'],
            [['id_destino'], 'integer'],
            [['tipo', 'cumple'], 'string'],
            [['cumple'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tipo' => 'Tipo',
            'id_destino' => 'Destino',
        ];
    }

    public function getCondicion()
    {
        return $this->hasMany(Condicion::className(), ['id_regla' => 'id']);
    }

    public function getConceptoDestino()
    {
        return $this->hasOne(ConceptoDestino::className(), ['id' => 'id_destino']);
    }
}
