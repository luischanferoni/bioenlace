<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "cie10".
 *
 * @property string $codigo
 * @property string $diagnostico
 *
 * @property DiagnosticoConsultas[] $diagnosticoConsultas
 * @property Consultas[] $idConsultas
 */
class Cie10 extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'cie10';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['codigo'], 'required'],
            [['codigo'], 'string', 'max' => 4],
            [['diagnostico'], 'string', 'max' => 250]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'codigo' => 'Codigo',
            'diagnostico' => 'Diagnostico',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDiagnosticoConsultas()
    {
        return $this->hasMany(DiagnosticoConsultas::className(), ['codigo' => 'codigo']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdConsultas()
    {
        return $this->hasMany(Consultas::className(), ['id_consulta' => 'id_consulta'])->viaTable('diagnostico_consultas', ['codigo' => 'codigo']);
    }
    
    public function getCie10Concat() {
        return $this->codigo.' - '.$this->diagnostico;
    }
    
}
