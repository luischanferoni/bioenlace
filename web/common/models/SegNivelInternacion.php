<?php

namespace common\models;

use Yii;
use common\models\DiagnosticoConsultaRepository as DCRepo;

/**
 * This is the model class for table "seg_nivel_internacion".
 *
 * @property int $id
 * @property string|null $fecha_inicio
 * @property string|null $hora_inicio
 * @property string|null $fecha_fin
 * @property string|null $hora_fin
 * @property string|null $observaciones_alta 
 * @property string|null $condiciones_derivacion 
 * @property string|null $situacion_al_ingresar 
 * @property int|null $id_tipo_alta 
 * @property int|null $id_cama
 * @property int|null $id_persona
 * @property int $id_rrhh 
 * @property string $created_at 
 * @property int $create_user 
 * @property string|null $updated_at 
 * @property int|null $update_user 
 *
 * @property InfraestructuraCama $cama
 * @property SegNivelInternacionTipoAlta $tipoAlta
 * @property Efectores $efectorOrigen
 * @property Efector $efectorDerivacion
 * @property SegNivelInternacionTipoIngreso $tipoIngreso
 * @property SegNivelInternacionAtencionesEnfermeria[] $segNivelInternacionAtencionesEnfermerias
 * @property SegNivelInternacionDiagnostico[] $segNivelInternacionDiagnosticos
 * @property SegNivelInternacionMedicamento[] $segNivelInternacionMedicamentos
 * @property SegNivelInternacionPractica[] $segNivelInternacionPracticas
 */



class SegNivelInternacion extends \yii\db\ActiveRecord
{

    const INGRESO_EN = ['deambula' => 'Deambulando (Caminando)', 'silla_de_rueda' => 'Silla de Rueda', 'camilla' => 'Camilla'];
    const INGRESO_CON = ['solo' => 'Solo', 'familiar' => 'Familiar', 'policia' => 'Personal Policial', 'otro' => 'Otro', 'no_sabe' => 'No sabe/No contesta'];
    const TIPO_INGRESO_DERIVACION = 4;
    const TIPO_INGRESO = [
        1=>'Guardia',
        2=>'Consultorio',
        3=>'Programada',
        4=>'Derivación',
        5=>'No especificado',
        6=>'Quirofano'];
    public $alta_hospitalaria;

    const INGRESO_PACIENTE = 'ingresoPaciente';
    const EGRESO_PACIENTE = 'egresoPaciente';
    const TIPO_ALTA_DERIVACION_CMC = 5;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion';
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
                'value' => Yii::$app->user->id
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return
            [
                [['fecha_inicio','hora_inicio', 'fecha_fin', 'hora_fin'], 'safe'],
                [['observaciones_alta', 'condiciones_derivacion', 'situacion_al_ingresar', 'ingresa_en', 'ingresa_con', 'datos_contacto_nombre', 'datos_contacto_tel'], 'string'],
                [['id_tipo_alta', 'id_efector_derivacion', 'id_cama', 'id_persona', 'id_rrhh', 'created_by', 'updated_by', 'obra_social'], 'integer'],
                [['id_cama'], 'exist', 'skipOnError' => true, 'targetClass' => InfraestructuraCama::className(), 'targetAttribute' => ['id_cama' => 'id']],
                [['id_tipo_alta'], 'exist', 'skipOnError' => true, 'targetClass' => SegNivelInternacionTipoAlta::className(), 'targetAttribute' => ['id_tipo_alta' => 'id']],
                [['id_efector_derivacion'], 'exist', 'skipOnError' => true, 'targetClass' => Efector::className(), 'targetAttribute' => ['id_efector_derivacion' => 'id_efector']],
                [['id_tipo_ingreso'], 'exist', 'skipOnError' => true, 'targetClass' => SegNivelInternacionTipoIngreso::className(), 'targetAttribute' => ['id_tipo_ingreso' => 'id']],
                
                ['fecha_inicio', 'date', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida', 'on' => self::INGRESO_PACIENTE],
                [['fecha_inicio', 'hora_inicio', 'id_rrhh', 'id_tipo_ingreso'], 'required', 'on' => self::INGRESO_PACIENTE],
                [['fecha_fin', 'hora_fin', 'id_tipo_alta'], 'required', 'on' => self::EGRESO_PACIENTE],
                [
                    ['id_efector_origen'],
                    'required', 
                    'when' => function ($model) {
                        return ($model->id_tipo_ingreso == self::TIPO_INGRESO_DERIVACION);
                    },
                    'whenClient' => "function (attribute, value) {
                        var tipo_ingreso = $('#id_tipo_ingreso').val();
                        alert(tipo_ingreso);
                        return tipo_ingreso = ".self::TIPO_INGRESO_DERIVACION.";
                    }",
                    'on' => self::INGRESO_PACIENTE
                ],
                [
                    ['id_efector_origen'], 
                    'exist', 
                    'skipOnError' => true,
                    'targetClass' => Efector::className(),
                    'targetAttribute' => ['id_efector_origen' => 'id_efector']
                ],
                [['ingresa_en', 'ingresa_con'], 'required', 'on' => self::INGRESO_PACIENTE],
                [['datos_contacto_nombre'], 'match', 'pattern' => '/^[A-ZÁÉÍÓÚÑa-záéíóúñ\s]+$/', 'message' => 'El campo solo debe contener letras'],
                ['datos_contacto_tel', 'required', 'when' => function ($model) {
                    if ($model->ingresa_con == 'familiar' || $model->ingresa_con == 'otro' || $model->ingresa_con == 'policia') {
                        return true;
                    }
                    return false;
                }, 'whenClient' => "function (attribute, value) {
            var radioVal = $('input[name=\'SegNivelInternacion[ingresa_con]\']:checked').val();
            if (radioVal == 'familiar'|| radioVal == 'otro' || radioVal == 'policia') {
                return true;
            }
            return false;                
                }"],
                ['datos_contacto_nombre', 'required', 'when' => function ($model) {
                    if ($model->ingresa_con == 'familiar' || $model->ingresa_con == 'otro' || $model->ingresa_con == 'policia') {
                        return true;
                    }
                    return false;
                }, 'whenClient' => "function (attribute, value) {
            var radioVal = $('input[name=\'SegNivelInternacion[ingresa_con]\']:checked').val();
            if (radioVal == 'familiar'|| radioVal == 'otro' || radioVal == 'policia') {
                return true;
            }
            return false;                
              }"],
              [
                ['id_efector_derivacion'],
                'required', 
                'when' => function ($model) {
                    return ($model->id_tipo_alta == self::TIPO_ALTA_DERIVACION_CMC);
                },
                'on' => self::EGRESO_PACIENTE
            ],
            [
                ['id_tipo_alta'],
                'validateExternacion',
                'on' => self::EGRESO_PACIENTE
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
            'fecha_inicio' => 'Fecha Ingreso',
            'hora_inicio' => 'Hora Ingreso',
            'fecha_fin' => 'Fecha Alta',
            'hora_fin' => 'Hora Alta',
            'id_cama' => 'Nro Cama',
            'id_rrhh' => 'Profesional que solicita Internación',
            'id_persona' => 'Paciente',
            'observaciones_alta' => 'Observaciones',
            'condiciones_derivacion' => 'Condiciones de Derivación',
            'situacion_al_ingresar' => 'Observaciones',
            'id_tipo_alta' => 'Tipo Alta',
            'id_efector_derivacion' => 'Lugar de derivación',
            'id_efector_origen' => 'Lugar de origen de derivación',
            'id_tipo_ingreso' => 'Tipo Ingreso',
            'ingresa_en' => 'Cómo ingresa:',
            'ingresa_con' => 'Con quién ingresa:',
            'datos_contacto_nombre' => 'Nombre del Acompañante',
            'datos_contacto_tel' => 'Telefono del Acompañante',
            'obra_social' => 'Cobertura Medica del Paciente',
        ];
    }

    /**
     * Gets query for [[Cama]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCama()
    {
        return $this->hasOne(InfraestructuraCama::className(), ['id' => 'id_cama']);
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
     * Gets query for [[Consulta]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAtenciones() {
        return $this
            ->hasMany(Consulta::className(), ['parent_id' => 'id'])
            ->onCondition(['parent_class' => '\common\models\SegNivelInternacion'])
            ->orderBy(['created_at' => SORT_DESC]);
    }

   

    /**
     * Gets query for [[SegNivelInternacionDiagnosticos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSegNivelInternacionDiagnosticos()
    {
        return $this->hasMany(SegNivelInternacionDiagnostico::className(), ['id_internacion' => 'id']);
    }

    /**
     * Gets query for [[SegNivelInternacionPracticas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSegNivelInternacionPracticas()
    {
        return $this->hasMany(SegNivelInternacionPractica::className(), ['id_internacion' => 'id']);
    }

    /**
     * Gets query for [[SegNivelInternacionMedicamentos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSegNivelInternacionMedicamentos()
    {
        return $this->hasMany(SegNivelInternacionMedicamento::className(), ['id_internacion' => 'id']);
    }

    /**
     * Gets query for [[SegNivelInternacionSuministroMedicamentos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSegNivelInternacionSuministroMedicamentos()
    {
        return $this->hasMany(SegNivelInternacionSuministroMedicamento::className(), ['id_internacion' => 'id'])->orderBy(['fecha' => 'SORT_ASC', 'hora' => 'SORT_ASC']);
    }

    /**
     * Gets query for [[SegNivelInternacionAtencionesEnfermeria]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSegNivelInternacionAtencionesEnfermeria()
    {
        return $this->hasMany(SegNivelInternacionAtencionesEnfermeria::className(), ['id_internacion' => 'id']);
    }

    /**
     * Gets query for [[TipoAlta]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTipoAlta()
    {
        return $this->hasOne(SegNivelInternacionTipoAlta::className(), ['id' => 'id_tipo_alta']);
    }

    /**
     * Gets query for [[EfectorOrigen]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getEfectorOrigen()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector_origen']);
    }

    /**
     * Gets query for [[EfectorDerivacion]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getEfectorDerivacion()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector_derivacion']);
    }

    /**
     * Gets query for [[TipoIngreso]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTipoIngreso()
    {
        return $this->hasOne(SegNivelInternacionTipoIngreso::className(), ['id' => 'id_tipo_ingreso']);
    }

    public function getRrhh()
    {
        return $this->hasOne(RrhhServicio::className(), ['id' => 'id_rrhh']);
    }

    /**
     * Obtiene las internaciones en curso
     */
    public function getInternacionesActuales()
    {
        //Todo: para que sirve este metodo? no esta filtrado por efector
        $efector = Yii::$app->user->getIdEfector();
        return SegNivelInternacion::find()
                ->where(['<=', 'fecha_inicio', date('Y-m-d')])
                ->andWhere(['is', 'fecha_fin', NULL])
                ->one();
    }

    //Consulto si el paciente esta internado actualmente.
    public static function personaInternada($id_persona)
    {
        $datosInternacion = self::find()
            ->where(['id_persona' => $id_persona])
            ->andWhere(['is', 'fecha_fin', NULL])
            ->one();

        return $datosInternacion;        
    }

    public static function personaInternadaEnEfector($id_persona, $idEfector)
    {
        $datosInternacion = self::find()
            ->where(['id_persona' => $id_persona])
            ->innerJoin('infraestructura_cama', 'infraestructura_cama.id = seg_nivel_internacion.id_cama')
            ->innerJoin('infraestructura_sala', 'infraestructura_sala.id = infraestructura_cama.id_sala')
            ->innerJoin('infraestructura_piso', 'infraestructura_piso.id = infraestructura_sala.id_piso')
            ->andWhere(['infraestructura_piso.id_efector' => $idEfector])
            ->andWhere(['is', 'fecha_fin', NULL])
            ->one();

        if ($datosInternacion) {
            return $datosInternacion->id;
        }
        return false;
    }

    //Metodo para consultar si la internacion esta activa.
    public static function internacionActiva($id)
    {
        $datosInternacion = self::find()
            ->where(['id' => $id])
            ->andWhere(['is', 'fecha_fin', NULL])
            ->all();

        if (count($datosInternacion) > 0) {
            return true;
        }
        return false;
    }

    public static function fechaAlta($id)
    {
        $datosInternacion = self::find()
        ->where(['id'=>$id])
        ->one();

        $fechaAltaInternacion = $datosInternacion->fecha_fin." ".$datosInternacion->hora_fin;

        return $fechaAltaInternacion;
    }

    public function internacionConAlta(){

        $tieneAlta = $this->fecha_fin;

        if($tieneAlta != NULL){

            return true;

        }else{

            return false;
        }

    }

    public function beforeSave($insert)
    {
        parent::beforeSave($insert);

        if ($this->scenario == 'ingresoPaciente') {
            $fecha = date_create_from_format('d/m/Y', $this->fecha_inicio);
            $fechaFormateada = date_format($fecha, 'Y-m-d');
            $this->fecha_inicio = $fechaFormateada;
        }

        if ($this->scenario == 'egresoPaciente') {
            $fechaFin = date_create_from_format('d/m/Y', $this->fecha_fin);
            $fechaFinFormateada = date_format($fechaFin, 'Y-m-d');
            $this->fecha_fin = $fechaFinFormateada;
        }

        return true;
    }

    public function enableExternacion() {
        // Logica para habilitar boton externacion o no.
        return (null == $this->id_tipo_alta);
    }
    
    public function enableCambioCama() {
        // Logica para habilitar boton cambio cama.
        return (null == $this->id_tipo_alta);
    }
    
    public function validateExternacion() {
        $diagnosticos_cargados = DCRepo::getCountDiagnosticosIMP($this);
        if($diagnosticos_cargados == 0) {
            $msg = "No se registran diagnósticos cargados. "
                   ."Debe solicitar al profesional a cargo que ingrese "
                   ."un diagnóstico para poder realizar el alta del paciente.";

            $this->addError('*', $msg);
        }
    }


    public static function footerTimeline($id_internacion)
    {

        $internacion = self::findOne($id_internacion);
        $efectorEnSesion = Yii::$app->user->getIdEfector();

        if($internacion->cama->sala->piso->id_efector == $efectorEnSesion && !$internacion->internacionConAlta()){

           $tituloBoton = 'Ir a Internación';
           $a = '<p class="float-left mb-3 mt-1"> Estado: <span class="badge bg-soft-success">ACTIVA</span></p>';

        }elseif($internacion->internacionConAlta()){

            $a = '<p class="float-left mb-3 mt-1"> Estado: <span class="badge bg-soft-secondary">FINALIZADA</span></p>';
            $tituloBoton = 'Ver Detalle';

        }else{
            $a = '<p class="float-left mb-3 mt-1"> Estado: <span class="badge bg-soft-secondary">ACTIVA</span></p>';
            $tituloBoton = 'Ver Detalle';
        }

        $b = yii\helpers\Html::a(
            $tituloBoton,
            ['internacion/'.$id_internacion],
            [
                'class' => 'btn btn-sm btn-outline-info rounded-pill', 
                'title' => 'Internacion',
                'target' => '_blank'
            ]
        );  

        $footer = $a . $b;

        return $footer;
    }



}
