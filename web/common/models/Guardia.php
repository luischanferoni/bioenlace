<?php

namespace common\models;

use Yii;
use yii\helpers\Console;

/**
 * This is the model class for table "guardia".
 *
 * @property int $id
 * @property int|null $id_persona
 * @property string|null $fecha
 * @property string|null $hora
 * @property string|null $fecha_fin
 * @property string|null $hora_fin
 * @property string|null $estado
 * @property int|null $id_rrhh_asignado
 * @property string|null $cobertura
 * @property string|null $situacion_al_ingresar
 * @property int|null $id_efector_derivacion
 * @property string|null $condiciones_derivacion
 * @property int|null $notificar_internacion_id_efector
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $cobertura
 * @property string|null $situacion_al_ingresar
 * @property int|null $id_efector_derivacion
 * @property string|null $condiciones_derivacion
 * @property int|null $notificar_internacion_id_efector
 * @property int|null $id_efector
 */
class Guardia extends \yii\db\ActiveRecord
{
    const INGRESO_EN = ['deambula' => 'Deambulando (Caminando)', 'silla_de_rueda' => 'Silla de Rueda', 'camilla' => 'Camilla'];
    const INGRESO_CON = ['solo' => 'Solo', 'familiar' => 'Familiar', 'policia' => 'Personal Policial', 'otro' => 'Otro', 'no_sabe' => 'No sabe/No contesta'];
    const TIPO_INGRESO_DERIVACION = 4;

    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_ATENDIDA = 'atendida';
    const ESTADO_FINALIZADA = 'finalizada';

    public $alta_hospitalaria;

    const INGRESO_PACIENTE = 'ingresoPaciente';
    const EGRESO_PACIENTE = 'egresoPaciente';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'guardia';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_DELETE => ['deleted_by'],
                ],
                'value' => Yii::$app->user->id,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_persona', 'id_rrhh_asignado', 'created_by', 'updated_by', 'deleted_by', 'id_efector_derivacion', 'notificar_internacion_id_efector', 'id_efector',], 'integer'],
            [['fecha', 'hora', 'fecha_fin', 'hora_fin', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['ingresa_con', 'ingresa_en', 'estado', 'situacion_al_ingresar', 'condiciones_derivacion', 'datos_contacto_tel'], 'string'],
            [['cobertura'], 'string', 'max' => 100],
            [['id_persona'], 'validarCombinacionUnica', 'on' => self::INGRESO_PACIENTE],
            [['ingresa_en', 'ingresa_con', 'fecha'], 'required', 'on' => self::INGRESO_PACIENTE],
            [['fecha_fin', 'hora_fin'], 'required', 'on' => self::EGRESO_PACIENTE],
            ['fecha', 'date', 'min' => strtotime(date("Y-m-d") . ' - 1 days'), 'tooSmall' => 'Solamente hasta el día de ayer', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida', 'on' => self::INGRESO_PACIENTE],
            [
                'datos_contacto_tel',
                'required',
                'when' => function ($model) {
                    if ($model->ingresa_con == 'familiar' || $model->ingresa_con == 'otro' || $model->ingresa_con == 'policia') {
                        return true;
                    }
                    return false;
                },
                'whenClient' => "function (attribute, value) {
                    var radioVal = $('input[name=\'Guardia[ingresa_con]\']:checked').val();
                    if (radioVal == 'familiar'|| radioVal == 'otro' || radioVal == 'policia') {
                        return true;
                    }
                    return false;                
                }"
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_persona' => 'Paciente',
            'fecha' => 'Fecha Ingreso',
            'hora' => 'Hora Ingreso',
            'id_rrhh_asignado' => 'Profesional',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
            'cobertura' => 'Cobertura',
            'situacion_al_ingresar' => 'Situacion Al Ingresar',
            'id_efector_derivacion' => 'Se deriva al paciente a',
            'condiciones_derivacion' => 'Condiciones Derivacion',
            'notificar_internacion_id_efector' => 'Notificar Internacion al Efector',
            'ingresa_en' => 'Manera de ingreso',
            'ingresa_con' => 'Acompañante',
            'datos_contacto_nombre' => 'Nombre del Acompañante',
            'datos_contacto_tel' => 'Telefono del Acompañante',
            'obra_social' => 'Cobertura Medica del Paciente',
            'id_efector' => 'Id Efector',
        ];
    }

    /**
     * Gets query for [[Persona]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPaciente()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[RrhhAsignado]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhEfector()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_rrhh_asignado']);
    }

    public function getRrhhServicio()
    {
        return $this->hasOne(RrhhServicio::className(), ['id' => 'id_rrhh_asignado']);
    }

    public function validarCombinacionUnica($attribute, $params, $validator)
    {
        $existe = self::find()
            ->where([
                'id_persona' => $this->id_persona,
                'estado' => 'pendiente',
                'id_efector' => $this->id_efector,
            ])
            ->exists();

        if ($existe) {
            $this->addError($attribute, 'La persona ya se encuentra en la guardia de este efector.');
        }
    }

    public static function pacienteIngresadoEnEfector($idPersona, $idEfector)
    {
        $guardia = self::find()
            ->where(['id_persona' => $idPersona])
            ->andWhere(['fecha' => date("Y-m-d")])
            ->andWhere(['id_efector' => $idEfector])
            ->andWhere(['estado' => self::ESTADO_PENDIENTE])
            ->one();


        return $guardia;
    }
    public static function pacienteIngresado($idPersona)
    {
        $guardia = self::find()
            ->where(['id_persona' => $idPersona])
            ->andWhere(['fecha' => date("Y-m-d")])
            ->andWhere(['estado' => self::ESTADO_PENDIENTE])
            ->one();

        return $guardia;
    }

    public static function pacientesPendientesPorEfector($idEfector)
    {
        $guardias = self::find()
            ->andWhere(['id_efector' => $idEfector])
            ->andWhere(['estado' => self::ESTADO_PENDIENTE])
            ->all();

        return $guardias;
    }

    public static function footerTimeline($tipo, $id, $id_persona)
    {

        $guardia = self::findOne($id);
        $encounterClass = Yii::$app->user->getEncounterClass();

        $a = '';

        switch ($tipo) {

            case 'pendiente':
                if ($guardia->id_efector == Yii::$app->user->getIdEfector()) {

                    if ($encounterClass ==  Consulta::ENCOUNTER_CLASS_EMER) {

                        $url = Consulta::armarUrlAConsultadesdeParent(Consulta::PARENT_GUARDIA, $id, '', $id_persona);

                        $a = yii\helpers\Html::a(
                            'Atender',
                            $url,
                            [
                                'class' => 'btn btn-sm btn-outline-info rounded-pill atender',
                                'title' => 'Atender',
                            ]
                        );
                    } else {

                        $a = '<span class="badge bg-soft-warning">Para atender esta guardia usted debe cambiar al ambito "EMERGENCIA".</span>';
                    }

                } else {
                    $a = '<span class="badge bg-soft-warning">Esta guardia corresponde a otro efector.</span>';
                }
                break;

            default:
                break;
        }

        $footer = $a;

        return $footer;
    }


    public function beforeSave($insert)
    {
        parent::beforeSave($insert);

        if ($this->scenario == 'ingresoPaciente') {
            $fecha = date_create_from_format('d/m/Y', $this->fecha);
            $fechaFormateada = date_format($fecha, 'Y-m-d');
            $this->fecha = $fechaFormateada;
            $this->created_at = date('Y-m-d  H:i:s');
            $this->estado = 'pendiente';
        }

        if ($this->scenario == 'egresoPaciente') {
            $fechaFin = date_create_from_format('d/m/Y', $this->fecha_fin);
            $fechaFinFormateada = date_format($fechaFin, 'Y-m-d');
            $this->fecha_fin = $fechaFinFormateada;
            $this->updated_at = date('Y-m-d H:i:s');
            $this->estado = 'finalizada';
        }

        return true;
    }
}
