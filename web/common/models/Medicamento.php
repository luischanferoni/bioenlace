<?php

/**
* @autor: Ivana Beltrán y María de los A. Valdez
* @versión: 1.1 
* @creacion: 25/02/2016
* @modificacion: 
**/

namespace common\models;

use Yii;

/**
 * This is the model class for table "medicamentos".
 *
 * @property integer $id_medicamento
 * @property string $generico
 * @property string $presentacion
 *
 * @property MedicamentosConsultas[] $medicamentosConsultas
 * @property Consultas[] $idConsultas
 */
class Medicamento extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'medicamentos';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['generico', 'presentacion'], 'string', 'max' => 60]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_medicamento' => 'Id Medicamento',
            'generico' => 'Generico',
            'presentacion' => 'Presentacion',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMedicamentosConsultas()
    {
        return $this->hasMany(MedicamentosConsultas::className(), ['id_medicamento' => 'id_medicamento']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdConsultas()
    {
        return $this->hasMany(Consultas::className(), ['id_consulta' => 'id_consulta'])->viaTable('medicamentos_consultas', ['id_medicamento' => 'id_medicamento']);
    }
    
    public function getMedicamentoConcat() {
        return $this->generico.' - '.$this->presentacion;
    }
}
