<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "consulta_ia".
 *
 * @property int $id
 * @property int $id_consulta
 * @property string|null $detalle
 */
class ConsultaIa extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_ia';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta'], 'required'],
            [['id_consulta'], 'integer'],
            [['detalle'], 'string'],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_consulta' => 'Id Consulta',
            'detalle' => 'Detalle',
        ];
    }

}