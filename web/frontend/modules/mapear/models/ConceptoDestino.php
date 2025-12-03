<?php

namespace frontend\modules\mapear\models;

use Yii;

/**
 * This is the model class for table "concepto_destino".
 *
 * @property int $id
 * @property string $codigo
 * @property string $concepto
 */
class ConceptoDestino extends MapearActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'concepto_destino';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['codigo', 'concepto'], 'required'],
            [['codigo', 'concepto'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'codigo' => 'Codigo',
            'concepto' => 'Concepto',
        ];
    }

    /**
     * {@inheritdoc}
     * @return LaboratorioQuery the active query used by this AR class.
     */
    // public static function find()
    // {
    //     return new ConceptoDestinoQuery(get_called_class());
    // }
}
