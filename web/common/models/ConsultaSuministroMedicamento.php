<?php

namespace common\models;

use Yii;
use common\models\snomed\SnomedMedicamentos;


/**
 * This is the model class for table "seg_nivel_internacion_medicamento".
 *
 * @property int $id
 * @property string|null $id_internacion_medicamento
 * @property string|null $id_internacion
 * @property string|null $fecha
 * @property string|null $hora
 * @property int|null $id_rrhh
 * @property string|null $observaciones
 */
class ConsultaSuministroMedicamento extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_suministro_medicamento';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            //[['id_internacion_medicamento'], 'required'],
            [['id', 'id_internacion_medicamento', 'id_rrhh','id_consulta'], 'integer'],
            [['fecha', 'hora'], 'required'],
            [['observacion'], 'safe'],
            [['id'], 'unique'],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            "Fecha",
            "Hora",
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'fecha' => 'Fecha',
            'Hora' => 'Hora',
            'observacion' => 'Observación',
        ];
    }

    /**
     * Gets query for [[RrhhSuministra]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhSuministra()
    {
        return $this->hasOne(Rrhh_efector::className(), ['id_rr_hh' => 'id_rrhh']);
    }

        /**
     * Gets query for [[InternacionMedicamento]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInternacionMedicamento()
    {
        return $this->hasOne(SegNivelInternacionMedicamento::className(), ['id' => 'id_internacion_medicamento']);
    }

    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }
    
}
