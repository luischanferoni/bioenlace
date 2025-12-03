<?php

namespace common\models;

use common\models\snomed\SnomedHallazgos;
use common\models\DiagnosticoConsultaRepository as DCRepo;

use Yii;

/**
 * This is the model class for table "diagnostico_consultas".
 *
 * @property string $id_consulta
 * @property string $codigo
 * @property string $tipo_diagnostico
 *
 * @property Cie10 $codigo0
 * @property Consultas $idConsulta
 */
class DiagnosticoConsulta extends \yii\db\ActiveRecord
{
    # Comento la linea de abajo, ya que no se implementaron
    # los cambios necesarios para usar el trait SoftDeleteDateTimeTrait
    # en esta clase.
    # use \common\traits\SoftDeleteDateTimeTrait;

    const CLINICAL_STATUS_ACTIVE = 'ACTIVE';
    const CLINICAL_STATUS_RECURRENCE = 'RECURRENCE';
    const CLINICAL_STATUS_RELAPSE = 'RELAPSE';
    const CLINICAL_STATUS_INACTIVE = 'INACTIVE';
    const CLINICAL_STATUS_REMISSION = 'REMISSION';
    const CLINICAL_STATUS_RESOLVED = 'RESOLVED';
    const CLINICAL_STATUS_UNKNOWN = 'UNKNOWN';

    const VERIFICATION_STATUS_UNCONFIRMED = 'UNCONFIRMED';
    const VERIFICATION_STATUS_PROVISIONAL = 'PROVISIONAL';
    const VERIFICATION_STATUS_DIFFERENTIAL = 'DIFFERENTIAL';
    const VERIFICATION_STATUS_CONFIRMED = 'CONFIRMED';
    const VERIFICATION_STATUS_REFUTED = 'REFUTED';
    const VERIFICATION_STATUS_ENTERED_IN_ERROR = 'ENTERED_IN_ERROR';

    const ESTADOS_CLINICOS = [
        self::CLINICAL_STATUS_ACTIVE => 'Activo',
        self::CLINICAL_STATUS_RECURRENCE => 'Activo-Reaparición',
        self::CLINICAL_STATUS_RELAPSE => 'Activo-Recaida',
        self::CLINICAL_STATUS_INACTIVE => 'Inactivo',
        self::CLINICAL_STATUS_REMISSION => 'Inactivo-Remisión',
        self::CLINICAL_STATUS_RESOLVED => 'Inactivo-Resuelto',
    ];

    const ESTADOS_DE_VERIFICACION = [
        self::VERIFICATION_STATUS_UNCONFIRMED => 'Sin confirmar',
        self::VERIFICATION_STATUS_PROVISIONAL => 'Presuntivo',
        self::VERIFICATION_STATUS_DIFFERENTIAL => 'Diferencial',
        self::VERIFICATION_STATUS_CONFIRMED => 'Confirmado',
        self::VERIFICATION_STATUS_REFUTED => 'Refutado',
        self::VERIFICATION_STATUS_ENTERED_IN_ERROR => 'Ingresado por error',
    ];
    public $terminos_motivos;
    public $id_servicio;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'diagnostico_consultas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_consulta', 
              'codigo',
              'condition_clinical_status',
              'condition_verification_status',
              ], 'required'],
            [['id_consulta','id_servicio'], 'integer'],
            [['condition_clinical_status'],
                'default', 
                'value'=> self::CLINICAL_STATUS_ACTIVE],
            [['condition_verification_status'],
                'default', 
                'value'=> self::VERIFICATION_STATUS_PROVISIONAL],
            [['tipo_diagnostico', 
              'cronico', 
              'condition_verification_status', 
              'condition_clinical_status', 'terminos_motivos'], 'string'],
            [['codigo'], 'string', 'max' => 25],
            [['cronico'], 'default', 'value' => 'NO'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_consulta' => 'Id Consulta',
            'codigo' => 'Concepto',
            'tipo_diagnostico' => 'Tipo de Diagnóstico',
            'cronico' => 'Crónico',
            'condition_verification_status' => 'Estado de Verificación',
            'condition_clinical_status' => 'Estado Clínico',
            'tipo_prestacion' => 'Tipo de Prestación',
            'objeto_prestacion' => 'Objeto de la Prestación',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodigo0()
    {
        return $this->hasOne(Cie10::className(), ['codigo' => 'codigo']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }

    //Busca los diagnósticos por consulta
    public static function getDiagnosticoPorConsulta($id_cons)
    {
        $diagnostico = DiagnosticoConsulta::findAll(['id_consulta' => $id_cons]);
        return $diagnostico;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodigoSnomed()
    {
        return $this->hasOne(SnomedHallazgos::className(), ['conceptId' => 'codigo']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMedicamentos()
    {
        return $this->hasMany(ConsultaMedicamentos::className(), ['id_consultas_diagnosticos' => 'id']);
    }

      /**
     * @return \yii\db\ActiveQuery
     */
    public function getPracticas()
    {
        return $this->hasMany(ConsultaPracticas::className(), ['id_consultas_diagnosticos' => 'id']);
    }

    /**
     * Mientras la consulta no este finalizada (nueva o editando) el usuario
     * puede hacer un hard delete
     */
    public static function hardDeleteGrupo($id_consulta, $ids)
    {
        if (count($ids) > 0 && isset($id_consulta) && $id_consulta != "" && $id_consulta != 0) {
            self::deleteAll([
                'AND',
                ['in', 'id', $ids],
                ['=', 'id_consulta', $id_consulta]
            ]);
        }
    }
    
    public function getDiagnosticoTerm(){
        $term = "?";
        $sm = $this->codigoSnomed;
        if($sm) {
            $term = $sm->term;
        }
        return $term;
    }
    
    public function getCondVerificationStatusDesc(){
        return DCRepo::getVerificationStatusDisplayLabel(
                $this->condition_verification_status
        );
    }
    
    public function getCondClinicalStatusDesc(){
        return DCRepo::getClinicalStatusDisplayLabel(
                $this->condition_clinical_status
        );
    }
    
    public function getStatusesDesc($separator='/') {
        $desc = sprintf('%s %s %s',
            DCRepo::getClinicalStatusDisplayLabel(
                    $this->condition_clinical_status),
            $separator,
            DCRepo::getVerificationStatusDisplayLabel(
                $this->condition_verification_status)
            );
        return $desc;
    }
    
    public function getFechaConsulta() {
        $fecha = '?';
        if($this->consulta) {
            $fecha = Yii::$app->formatter->asDateTime(
                $this->consulta->created_at,
                'php:d-m-Y');
        }
        return $fecha;
    }
    
    public function isCronico() {
        return $this->cronico == 'SI';
    }
}
