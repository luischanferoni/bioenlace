<?php

namespace common\models;

use Yii;
use common\models\snomed\SnomedMedicamentos;


/**
 * This is the model class for table "seg_nivel_internacion_medicamento".
 *
 * @property int $id
 * @property string|null $conceptId
 * @property int|null $cantidad
 * @property string|null $dosis_diaria
 * @property string|null $indicacion
 */
class SegNivelInternacionMedicamento extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_medicamento';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_internacion'], 'required'],
            [['id', 'cantidad'], 'integer'],
            [['conceptId'], 'string', 'max' => 25],
            [['dosis_diaria'], 'string', 'max' => 40],
            [['indicacion'], 'string', 'max' => 255],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'conceptId' => 'Concepto',
            'cantidad' => 'Cantidad',
            'dosis_diaria' => 'Dosis Diaria',
            'indicacion' => 'Indicacion',
        ];
    }

    /**
     * Gets query for [[SnomedMedicamentos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMedicamentoSnomed()
    {
        return $this->hasOne(SnomedMedicamentos::className(), ['conceptId' => 'conceptId']);
    }

    /**
     * Gets query for [[SnomedMedicamentos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMedicamentoSnomedNombre()
    {
        return $this->hasOne(SnomedMedicamentos::className(), ['conceptId' => 'conceptId'])->one()->term;
    }
}
