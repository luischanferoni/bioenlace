<?php

namespace common\models;

use Yii;
use common\models\snomed\SnomedProblemas;

/**
 * This is the model class for table "seg_nivel_internacion_diagnostico".
 *
 * @property int $id
 * @property int $id_internacion
 * @property string|null $conceptId
 *
 * @property SegNivelInternacion $internacion
 */
class SegNivelInternacionDiagnostico extends \yii\db\ActiveRecord
{

    const TIPO_PROBLEMA = ['sintoma'=>'Sintomas','diagnostico'=>'Diagnosticos'];
    const CONDITION_VERIFICATION_STATUS = ['unconfirmed'=>'Sin Confirmacion','provisional'=>'Provisional','differential'=>'Diferencial','confirmed'=>'Confirmado','refuted'=>'Refutado','entered-in-error'=>'Ingresado por Error'];
    const CONDITION_CLINICAL_STATUS = ['active'=>'Activo','recurrence'=>'Recurrente','relapse'=>'Recaida','inactive'=>'Inactivo','remission'=>'Remision','resolved'=>'Resuelto','unknown'=>'Desconocido'];
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_diagnostico';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_internacion','conceptId'], 'required'],
            [['id', 'id_internacion'], 'integer'],
            [['conceptId'], 'string', 'max' => 25],
            [['tipo_problema','condition_verification_status','condition_clinical_status'],'string'],
            [['created_at', 'updated_at','deleted_at'],'safe'],
            [['id', 'id_internacion'], 'unique', 'targetAttribute' => ['id', 'id_internacion']],
            [['id_internacion'], 'exist', 'skipOnError' => true, 'targetClass' => SegNivelInternacion::className(), 'targetAttribute' => ['id_internacion' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_internacion' => 'Id Internacion',
            'conceptId' => 'Concepto',
            'tipo_problema'=>'Sintoma/Diagnostico',
            'condition_verification_status'=>'Estado de Verificacion de la Condicion',
            'condition_clinical_status'=>'Estado Clinico de la Condicion'
        ];
    }

    /**
     * Gets query for [[Internacion]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInternacion()
    {
        return $this->hasOne(SegNivelInternacion::className(), ['id' => 'id_internacion']);
    }
    /**
     * Gets query for [[SnomedProblemas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDiagnosticoSnomed()
    {
        return $this->hasOne(SnomedProblemas::className(), ['conceptId' => 'conceptId']);
    }
}
