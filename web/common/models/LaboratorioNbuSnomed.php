<?php

namespace common\models;

use Yii;
use common\models\snomed\SnomedProcedimientos;

/**
 * This is the model class for table "laboratorio_nbu_snomed".
 *
 * @property int $id
 * @property int $codigo
 * @property string $conceptId
 */
class LaboratorioNbuSnomed extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'laboratorio_nbu_snomed';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['codigo'], 'required'],
            [['codigo'], 'integer'],
            [['conceptId'], 'string', 'max' => 25],
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
            'conceptId' => 'Concept ID',
        ];
    }

    public function getlaboratorioNbu()
    {
        return $this->hasOne(LaboratorioNbu::className(), ['codigo' => 'codigo']);
    }

    public function getSnomed()
    {
        return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'conceptId']);
    }
}
