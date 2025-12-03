<?php

namespace common\models;

use frontend\components\UserConfig;
use Yii;
use yii\data\SqlDataProvider;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\db\QueryInterface;
use common\models\sumar\Autofacturacion;

/**
 * This is the model class for table "consultas".
 *
 * @property string $id_consulta
 * @property string $id_turnos
 * @property string $hora
 * @property string $consulta_inicial
 * @property string $id_tipo_consulta
 * @property string $motivo_consulta
 * @property string $observacion
 * @property string $control_embarazo
 *
 * @property TipoConsultas $idTipoConsulta
 * @property Turnos $idTurnos
 * @property DiagnosticoConsultas[] $diagnosticoConsultas
 * @property Cie10[] $codigos
 * @property MedicamentosConsultas[] $medicamentosConsultas
 * @property Medicamentos[] $idMedicamentos
 */

class Consulta extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    // https://terminology.hl7.org/5.3.0/ValueSet-encounter-class.html
    const ENCOUNTER_CLASS_IMP = 'IMP'; // internacion, impatient encounter
    const ENCOUNTER_CLASS_AMB = 'AMB'; // ambulatory, consultorios externos
    const ENCOUNTER_CLASS_OBSENC = 'OBSENC'; 
    // observation encounter, no esta internado pero esta en observacion 
    //un periodo de tiempo preestablecido y despues se lo deriva
    const ENCOUNTER_CLASS_EMER = 'EMER'; // urgencia, guardia
    const ENCOUNTER_CLASS_VR = 'VR'; // virtual
    const ENCOUNTER_CLASS_HH = 'HH'; // el doctor se traslada a cierto lugar fuera del hospital para la consulta

    const PARENT_TURNO = 'TURNO';
    const PARENT_DERIVACION = 'DERIVACION';
    const PARENT_INTERNACION = 'INTERNACION';
    const PARENT_GENERICO_AMB = 'GENERICO_AMB';
    const PARENT_GENERICO_EMER = 'GENERICO_EMER';
    const PARENT_GUARDIA = 'GUARDIA';
    const PARENT_PASE_PREVIO = 'PASE_PREVIO';
    const PARENT_ENCUESTA_PARCHES = 'ENCUESTA_PARCHES';

    const PARENT_CLASSES = [
            self::PARENT_TURNO => '\common\models\Turno', 
            self::PARENT_DERIVACION => '\common\models\ConsultaDerivaciones', 
            self::PARENT_INTERNACION => '\common\models\SegNivelInternacion',
            self::PARENT_GENERICO_AMB => '\common\models\GenericoAMB',
            self::PARENT_GENERICO_EMER => '\common\models\GenericoEMER',
            self::PARENT_GUARDIA => '\common\models\Guardia',
            self::PARENT_PASE_PREVIO => '\common\models\ServiciosEfector', //revisar
            self::PARENT_ENCUESTA_PARCHES => '\common\models\EncuestaParchesMamarios'
        ];

    // Estados de consulta
    const ESTADO_EN_PROGRESO = 'EN_PROGRESO';
    const ESTADO_FINALIZADA = 'FINALIZADA';
    const ESTADO_CANCELADA = 'CANCELADA';
    const ESTADO_PENDIENTE = 'PENDIENTE';
    
    // Paso especial para consultas finalizadas (reemplaza el 999)
    const PASO_FINALIZADA = 999;

    public $urlAnterior;
    public $urlSiguiente;

    public $sistolica;
    public $diastolica;

    public $nombreservicio;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consultas';
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
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['parent_class', 'parent_id', 'id_persona', 'id_efector'], 'required'],
           /* ['sistolica', 'required', 'when' => function ($model) {
                $b = false;
                foreach ($model->diagnosticoConsultas as $diagnosticoConsulta) {
                    if($diagnosticoConsulta->codigo == '38341003'){
                        $b = true;
                    }
                }                
                return $b;
            }, 'whenClient' => "function (attribute, value) {
                    var b = false;
                    $('.diagnostico_select').each(function(index, value) {
                        if ($(this).val() == '38341003') {
                            b = true;
                        }
                    });
                return b;
            }"], */
           /* [['parent_id', 'parent_class', 'deleted_at'], 'unique' , 'targetAttribute' => ['parent_id', 'parent_class', 'deleted_at'],
                'message' => 
                'Esta consulta ya fue creada. '.HTML::a("Debe modificarla: ", Url::toRoute(['consultas/update'], true))],*/
            [['id_turnos', 'id_tipo_consulta'], 'integer'],
            [['hora'], 'safe'],
            [['consulta_inicial', 'motivo_consulta', 'observacion', 'control_embarazo'], 'string'],
            [['id_turnos','id_tipo_consulta'], 'default', 'value'=> 0]
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            'Tipo de origen de la consulta',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_consulta' => 'Id Consulta',
            'id_turnos' => 'Id de Turno',
            'hora' => 'Hora',
            'consulta_inicial' => 'Consulta',
            'id_tipo_consulta' => 'Tipo de consulta',
            'motivo_consulta' => 'Motivo de la consulta',
            'observacion' => 'Observación',
            'control_embarazo' => 'Control Embarazo',
            'parent_class' => 'Parent',
            'parent_id' => 'Parent Id',
            'paso_completado' => 'Paso completado',
            'id_configuracion' => 'Id Configuracion',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdTipoConsulta()
    {
        return $this->hasOne(TipoConsulta::className(), ['id_tipo_consulta' => 'id_tipo_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTurno()
    {
        return $this->hasOne(Turno::className(), ['id_turnos' => 'id_turnos']);
    }

    public function getRrhhEfector()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */    
    public function getIa()
    {
        return $this->hasMany(ConsultaIa::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDiagnosticoConsultas()
    {
        return $this->hasMany(DiagnosticoConsulta::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsultaPracticas()
    {
        return $this->hasMany(ConsultaPracticas::className(), ['id_consulta' => 'id_consulta'])->where(['tipo_practica'=>'POSTDIAGNOSTICO']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsultaEvaluaciones()
    {
        return $this->hasMany(ConsultaPracticas::className(), ['id_consulta' => 'id_consulta'])->where(['tipo_practica'=>'PREDIAGNOSTICO']);
    }

    public function getConsultaSolicitudPracticas()
    {
        return $this->hasMany(ConsultaDerivaciones::className(), ['id_consulta_solicitante' => 'id_consulta']);
    }

    public function getDerivacionesSolicitadas()
    {
        return $this->hasMany(ConsultaDerivaciones::className(), ['id_consulta_solicitante' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAlergias()
    {
        return $this->hasMany(Alergias::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodigos()
    {
        return $this->hasMany(Cie10::className(), ['codigo' => 'codigo'])->viaTable('diagnostico_consultas', ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsultaSintomas()
    {
        return $this->hasMany(ConsultaSintomas::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsultaMedicamentos()
    {
        return $this->hasMany(ConsultaMedicamentos::className(), ['id_consulta' => 'id_consulta'])
                    ->onCondition(['estado' => 'ACTIVO']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsultaObstetricia()
    {
        return $this->hasOne(ConsultaObstetricia::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsultaEvolucion()
    {
        return $this->hasOne(ConsultaEvolucion::className(), ['id_consulta' => 'id_consulta']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    /*public function getPracticasPersonaConsultas()
    {
        return $this->hasMany(PracticasPersona::className(), ['id_consulta' => 'id_consulta']);
    }*/
    
     /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersonasAntecedenteConsultas()
    {
        return $this->hasMany(PersonasAntecedente::className(), ['id_consulta' => 'id_consulta'])->where(['tipo_antecedente'=>'Personal']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersonasAntecedenteFamiliarConsultas()
    {
        return $this->hasMany(PersonasAntecedenteFamiliar::className(), ['id_consulta' => 'id_consulta'])->where(['tipo_antecedente'=>'Familiar']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
   /* public function getMedicamentos()
    {
        return $this->hasMany(Medicamentos::className(), ['id_consulta' => 'id_consulta']);
    }    */
    
    public function getPaciente()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }
    // TODO: Renombrar a MotivosConsulta
    public function getMotivoConsulta()
    {
        return $this->hasMany(ConsultaMotivos::className(), ['id_consulta' => 'id_consulta']);
    }

    public function getAutofacturacion()
    {
        return $this->hasOne(Autofacturacion::className(), ['id_consulta' => 'id_consulta']);
    }

    public function getOdontologiaEstados()
    {
        return $this->hasMany(ConsultaOdontologiaEstados::className(), ['id_consulta' => 'id_consulta']);
    }

    public function getOdontologiaPracticas()
    {
        return $this->hasMany(ConsultaOdontologiaPracticas::className(), ['id_consulta' => 'id_consulta']);
    }

    public function getOdontologiaDiagnosticos()
    {
        return $this->hasMany(ConsultaOdontologiaDiagnosticos::className(), ['id_consulta' => 'id_consulta']);
    }
    
     /**
     * @return \yii\db\ActiveQuery
     */
    public function getBalancesHidricos()
    {
        return $this->hasMany(ConsultaBalanceHidrico::className(), ['id_consulta' => 'id_consulta']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRegimenes()
    {
        return $this->hasMany(ConsultaRegimen::className(), ['id_consulta' => 'id_consulta']);
    }

    public function getOftalmologiasDP()
    {

        $dataProvider = new ActiveDataProvider([
            'query' => $this->hasMany(ConsultaPracticasOftalmologia::className(), ['id_consulta' => 'id_consulta'])->where(['tipo'=>'0']),
            'sort' =>false,
        ]);

        return $dataProvider;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOftalmologias()
    {
        return $this->hasMany(ConsultaPracticasOftalmologia::className(), ['id_consulta' => 'id_consulta'])->where(['tipo'=>'0']);
    }

    public function getOftalmologiasEstudiosDP()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => $this->hasMany(ConsultaPracticasOftalmologia::className(), ['id_consulta' => 'id_consulta'])->where(['tipo'=>'1']),
            'sort' =>false,
        ]);

        return $dataProvider;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOftalmologiasEstudios()
    {
        return $this->hasMany(ConsultaPracticasOftalmologia::className(), ['id_consulta' => 'id_consulta'])->where(['tipo'=>'1']);
    }

    public function getRecetasLentesDP()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => $this->hasOne(ConsultasRecetaLentes::className(), ['id_consulta' => 'id_consulta']),
            'sort' =>false,
        ]);

        return $dataProvider;
    }

    public function getRecetasLentes()
    {
        return $this->hasOne(ConsultasRecetaLentes::className(), ['id_consulta' => 'id_consulta']);
    }

    public function obtenerMotivoConsulta()
    {
        if ($this->id_motivo_consulta == 0) {
            return $this->motivo_consulta;
        } else {
            return $this->motivoConsulta;
        }
    }

    public function getMostUseRrhh($medico)
    {
        $connection = Yii::$app->getDb();
        $command = $connection->createCommand("
                        select c.id_servicio,s.nombre, sh.conceptId , sh.term, count(c.id_consulta) from consultas c
                        left join consultas_motivos cm on (c.id_consulta = cm.id_consulta)
                        LEFT JOIN snomed_hallazgos sh on (cm.codigo = sh.conceptId)
                        LEFT join servicios s on (c.id_servicio=s.id_servicio)
                        WHERE sh.conceptId is not null and c.id_rr_hh = :medico
                        GROUP by c.id_servicio, sh.conceptId, sh.term
                        ORDER by s.nombre asc, count(c.id_consulta) desc LIMIT 6",
                        [':medico' => $medico]);

        $result = $command->queryAll();
        $array = [];
        foreach ($result as $r):
            $array[$r['conceptId']] = $r['term'];
        endforeach;
        return $array;
    }

    public function getMostUseServicio($servicio)
    {
        $connection = Yii::$app->getDb();
        $command = $connection->createCommand("
                        select c.id_servicio,s.nombre, sh.conceptId , sh.term, count(c.id_consulta) from consultas c
                        left join consultas_motivos cm on (c.id_consulta = cm.id_consulta)
                        LEFT JOIN snomed_hallazgos sh on (cm.codigo = sh.conceptId)
                        LEFT join servicios s on (c.id_servicio=s.id_servicio)
                        WHERE sh.conceptId is not null
                        AND s.id_servicio = :servicio
                        GROUP by c.id_servicio, sh.conceptId, sh.term
                        ORDER by s.nombre asc, count(c.id_consulta) desc LIMIT 6",
            [':servicio' => $servicio]);

        $result = $command->queryAll();
        $array = [];
        foreach ($result as $r):
            $array[$r['conceptId']] = $r['term'];
        endforeach;
        return $array;
    }

    public function getIdpersonaConsulta($id_cons)
    {
        $query = Turno::find()
                        ->select('*'
                        )->from('turnos')
                        ->join('INNER JOIN','consultas', '`consultas`.`id_turnos` = `turnos`.`id_turnos`')
                        ->where(['`consultas`.`id_consulta`' => $id_cons])
                        ->one();
        
        return $query;
    }
    
    public static function getEdad($fecha_nac)
    {
        list($Y,$m,$d) = explode("-",$fecha_nac);
        return( date("md") < $m.$d ? date("Y")-$Y-1 : date("Y")-$Y );
        //return $Y;
    }
    
    public static function getEfector($id_turno)
    {
        $efector = Efector::find()
                        ->select('*'
                        )->from('turnos')
                        ->join('INNER JOIN','efectores', '`efectores`.`id_efector` = `turnos`.`id_efector`')
                        ->where(['`turnos`.`id_turnos`' => $id_turno])
                        ->one();
       $nombre_efector="";
        if(is_object($efector)){
            $nombre_efector=$efector->nombre;
        }
        return $nombre_efector;
    }

    public static function getEfectorByIdConsulta($id_consulta)
    {
        $efector = Efector::find()
            ->select('*'
            )->from('efectores')
            ->join('INNER JOIN','consultas', '`efectores`.`id_efector` = `consultas`.`id_efector`')
            ->where(['`consultas`.`id_consulta`' => $id_consulta])
            ->one();
        $nombre_efector="";
        if(is_object($efector)){
            $nombre_efector=$efector->nombre;
        }
        return $nombre_efector;
    }
    
    /**
     * Este metodo es para resolver la compatibilidad entre V1 y V2 de sisse
     * Se fija si la nueva columna id_persona tiene valor y devuelve de acuerdo a este
     * sino usa las columnas viejas
     */
    public function obtenerPaciente()
    {
        if ($this->id_persona != 0) {
            return $this->paciente;
        }

        if ($this->id_turnos != null && $this->id_turnos != 0 && $this->id_turnos != "") {
            return $this->turno->persona;
        }

        if ($this->parent_id != null && $this->parent_id != 0 && $this->parent_id != "") {
            if (isset($this->parent->persona)) {
                return $this->parent->persona;
            }
            if (isset($this->parent->paciente)) {
                return $this->parent->paciente;
            }
        }
    }    
      
    //Calcula la edad en años, meses y días
    public static function getEdad_bebe($fecha_nac)
    {
        //Calculo fecha de hoy
        $expression = new \yii\db\Expression('NOW()');
        $fecha_hora = (new \yii\db\Query)->select($expression)->scalar();
        list($fecha_hoy, $hora) = explode(" ", $fecha_hora);
        list($Y1, $m1, $d1) = explode("-", $fecha_hoy);
        list($Y, $m, $d) = explode("-", $fecha_nac);


        $anios = $Y1 - $Y; //Calculo años
        $meses = $m1 - $m; //Calculo meses
        $dias = $d1 - $d; //Calculo días
        //Veo si los días es un numero negativo        
        if ($dias < 0) {

            --$meses;

            //sumo a $dias los dias que tiene el mes anterior a la fecha de hoy 
            switch ($m1) {
                case 1: $dias_mes_anterior = 31;
                    break;
                case 2: $dias_mes_anterior = 31;
                    break;
                case 3:
                    if (self::bisiesto($Y1)) {
                        $dias_mes_anterior = 29;
                        break;
                    } else {
                        $dias_mes_anterior = 28;
                        break;
                    }
                case 4: $dias_mes_anterior = 31;
                    break;
                case 5: $dias_mes_anterior = 30;
                    break;
                case 6: $dias_mes_anterior = 31;
                    break;
                case 7: $dias_mes_anterior = 30;
                    break;
                case 8: $dias_mes_anterior = 31;
                    break;
                case 9: $dias_mes_anterior = 31;
                    break;
                case 10: $dias_mes_anterior = 30;
                    break;
                case 11: $dias_mes_anterior = 31;
                    break;
                case 12: $dias_mes_anterior = 30;
                    break;
            }

            $dias = $dias + $dias_mes_anterior;
        }

        //Si el numero de meses es negativo
        if ($meses < 0) {
            --$anios;
            $meses = $meses + 12;
        }
        // $edad_bebe =  $meses  . '  meses,  ' .  $dias  . '  días  ' ;
        if($anios!==0){
            $edad_bebe = $anios . '  años,  ' . $meses . '  meses,  ' . $dias . '  días  ';
        }
        else{
            $edad_bebe =  $meses . '  meses,  ' . $dias . '  días  ';
        }

        return $edad_bebe;
    }

    //Ver si el año es bisiesto
    static function bisiesto($anio_actual)
    {
        $bisiesto = false;
        //veo si el mes de febrero del año actual tiene 29 días 
        if (checkdate(2, 29, $anio_actual)) {
            $bisiesto = true;
        }
        return $bisiesto;
    }
    
    //datos antecedentes 
    public function getAntecedentesPersona($id_turno)
    {
        $ant_per = "";

        $antecedentes_persona= Antecedente::find()
                        ->select('*'
                        )->from('turnos')
                        ->join('INNER JOIN','consultas', '`consultas`.`id_turnos` = `turnos`.`id_turnos`')
                        ->join('INNER JOIN','personas_antecedentes', '`personas_antecedentes`.`id_consulta` = `consultas`.`id_consulta`')
                        ->join('INNER JOIN','antecedentes', '`antecedentes`.`id_antecedente` = `personas_antecedentes`.`id_antecedente`')
                        ->where(['`turnos`.`id_turnos`' => $id_turno])
                        ->all();
        
//        $antecedente_tipo = $antecedentes_persona->tipo;
//        $antecedente_nombre = $antecedentes_persona->nombre;
//        
//        return $antecedente_nombre.' ('.$antecedente_tipo.')';
        foreach($antecedentes_persona as $a_p) {
            $ant_per .= $a_p->nombre.'('.$a_p->tipo.'),';
        }
        return $ant_per;
    }
    
    public function getTiposPrestacion()
    {        
        return $rows = (new \yii\db\Query())
            ->select(['codigo', "CONCAT(codigo,' - ',categoria) as nombre"])
            ->from('grupo_prestacion')
            ->where(['tema' => 'PRESTACION'])
            ->orderBy(['codigo' => SORT_ASC])
            ->all();
        
    }
    
    public function getObjetosPrestacion($categoria_padre)
    {        
        return $rows = (new \yii\db\Query())
            ->select(['codigo', "CONCAT(codigo,' - ',categoria) as nombre"])
            ->from('grupo_prestacion')
            ->where(['tema' => 'OBJETO DE LA PRESTACION','categoria_padre' => $categoria_padre])
            ->orderBy(['codigo' => SORT_ASC])
            ->all();
    }
    
    public function getDiagnosticoCiap($codigo_cie10)
    {
        return $rows = (new \yii\db\Query())
            ->select(['codigo_ciap'])
            ->from('CIAP_CIE10_AP')
            ->where(['codigo' => $codigo_cie10])
            ->one();
    }

    public function getConsultasConPrescripcion2()
    {
        return Consulta::find()->join('turnos', ['id_turnos' => 'id_turnos'])
            ->join('medicamentos_consultas', ['id_consulta' => 'id_consulta'])
            ->where(['is not', 'medicamentos_consultas.id_consulta', NULL])
            ->addWhere(['=', 'id_efector', Yii::$app->user->getIdEfector()]);
    }

    public function getConsultasConPrescripcion()
    {
        $query = Consulta::find();

        $query->join = [
            ['INNER JOIN', 'turnos', 'consultas.id_turnos = turnos.id_turnos'],
            ['INNER JOIN', 'medicamentos_consultas', 'medicamentos_consultas.id_consulta = consultas.id_consulta']];
        $query->where(['is not', 'medicamentos_consultas.id_consulta', NULL]);
        $query->andWhere(['=', 'consultas.id_efector', Yii::$app->user->getIdEfector()]);
        $query->groupBy(['consultas.id_consulta']);
        $query->orderBy('turnos.fecha DESC');

         $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

         return $dataProvider;
    }

    //datos alergias 
    public function getAlergiasPersona($id_persona)
    {
        $alergias_persona= \common\models\Alergias::find()                        
                        ->where(['id_persona' => $id_persona])
                        ->all();
        return $alergias_persona;
    }

    public function getAtencionEnfermeria()
    {
        return $this
            ->hasOne(ConsultaAtencionesEnfermeria::className(), ['id_consulta' => 'id_consulta']);
    }

    public function getConsultaSuministroMedicamento()
    {
        return $this->hasMany(ConsultaSuministroMedicamento::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * Devuelve un array con todos los ids de los cuales es una edicion.
     * El campo editando hace referencia a la id de consulta que se esta editando
     * que a su vez puede ser la edicion de otra consulta, este metodo devuelve la cadena
     * de ids de consultas
     */
    public function getIdsEdiciones()
    {
        if (is_null($this->editando)) {
            return [];
        }

        $ids[] = $this->editando;
        $seguimo = true;

        while ($seguimo) {

            $consulta = self::find()
                ->where(['id_consulta' => $this->editando])
                ->one();
            
            if ($consulta) {
                if (is_null($consulta->editando)) {
                    $seguimo = false;
                } else {
                    $ids[] = $consulta->editando;
                }
            } else {
                $seguimo = false;
            }
        }

        return $ids;
    }

    /**
     * Devuelve la consulta correcta para cada uno de los pasos dependiendo de los parametros ingresados
     * En la tabla consulta se registra los pasos completados y se pasa por url el id_consulta a cada paso
     * Cada paso llama a este metodo
     * 
     * @param int $idConsulta: opcional, si recibido se verifica que la consulta no este en estado completado
     * @param int $editandoidConsulta: para saber si se esta editando una consulta, algunas verificaciones son diferentes en la edicion.
     * La edicion crea un nuevo modelo de Consulta seteando el campo editando con el id_consulta a editar
     * @param Persona $paciente
     * @param const PARENT_.. $parent: el origen de la consulta
     * @param int $parentId
     */
    public static function getModeloConsulta($idConsulta, Persona $paciente, string $parent = null, int $parentId = null, $anterior = null, $pasoEspecifico=  null)
    {
        // Si recibimos el id de consulta
        // las urls se pueden determinar de la tabla consultas

        if ($idConsulta != 0) {
            $modelConsulta = Consulta::findOne($idConsulta);

            if($anterior) {          
                $pasoCompletado = $modelConsulta->paso_completado - 1;      
                $modelConsulta->paso_completado = ($pasoCompletado == -1)? 0: $pasoCompletado;
                $modelConsulta->save();      
            }
            if($pasoEspecifico || $pasoEspecifico === 0) {                
                $modelConsulta->paso_completado = $pasoEspecifico;
                $modelConsulta->save();      
            }
            
            // paso_completado, el comienzo guardamos como 1 en lugar de 0 en BD
            // el segundo parametro de getUrlPorIdConfiguracion espera el paso que se necesita

            $fechaConsulta = new \DateTime($modelConsulta->created_at);
            $nuevasConsultas = new \DateTime("23-04-2024 11:15:01");

            $paso = $modelConsulta->paso_completado;

            if ($fechaConsulta < $nuevasConsultas) {
                $paso = $paso - 1;
            }

            list($urlAnterior, $urlActual, $urlSiguiente) = ConsultasConfiguracion::getUrlPorIdConfiguracion($modelConsulta->id_configuracion, $paso);
            
            if ($urlActual == null) {
                // Quiere decir que la consulta ya estaba finalizada
                // se intenta avanzar con una consulta que ya tiene todos los pasos
                Yii::warning('Intento continuar con una consulta ya finalizada');
                return ['success' => false, 'msg' => 'Esta consulta ya esta finalizada', 'model' => null, 'modelEditando' => null];
            }
            
            if ($urlAnterior != null) {
                $modelConsulta->urlAnterior = $urlAnterior . '?id_consulta=' . $modelConsulta->id_consulta.'&anterior=true' . '&id_persona=' .$paciente->id_persona;
            }

            if ($urlSiguiente == null) {
                $modelConsulta->urlSiguiente = 'fin';                
            } else {
                $modelConsulta->urlSiguiente = $urlSiguiente. '?id_consulta=' . $modelConsulta->id_consulta . '&id_persona=' .$paciente->id_persona;                
            }
            
            // si es 
            $modelEditando = null;
            if ($modelConsulta->editando) {
                $modelEditando = Consulta::findOne($modelConsulta->editando);
            }

            return ['success' => true, 'msg' => '', 'model' => $modelConsulta, 'modelEditando' => $modelEditando];
        }

        // No recibimos el id_consulta, debemos crear un nuevo modelo de consulta

        // determinamos el servicio, ambito (encounterClass) y el origen (parentClass)
        // este metodo tambien valida si el usuario puede atender a este paciente para dicho parent        
        $resultadoValidacion = ConsultasConfiguracion::validarPermisoAtencion($parent, $parentId, $paciente);
        
        if (!$resultadoValidacion['success']) {
            return [
                'success' => false, 
                'msg' => $resultadoValidacion['msg'], 
                'model' => null, 
                'modelEditando' => null
            ];
        }

        // La ejecucion de este metodo significa que ya estamos en el paso 0
        // entonces en urlSiguiente necesito el paso siguiente, el 1

        list($urlAnterior, $urlActual, $urlSiguiente, $idConfiguracion) = ConsultasConfiguracion::getUrlPorServicioYEncounterClass($resultadoValidacion['idServicio'], $resultadoValidacion['encounterClass']);
        
        if (!$idConfiguracion) {
            return [
                'success' => false, 
                'msg' => 'Error: Servicio sin configuración. Comuníquese con Salud Digital para resolver el inconveniente', 
                'model' => null, 
                'modelEditando' => null
            ];
        }

        // Al parecer solo tiene un paso
        $pasoCompletado = 0;
        if ($urlSiguiente == null) {
            $urlSiguiente = 'fin';
        }

        $modelConsulta = new Consulta();
        $modelConsulta->urlSiguiente = $urlSiguiente;
        $modelConsulta->urlAnterior = $urlAnterior;
        $modelConsulta->paso_completado = $pasoCompletado;
        $modelConsulta->id_configuracion = $idConfiguracion;

        $modelConsulta->parent_class = Consulta::PARENT_CLASSES[$parent];
        $modelConsulta->parent_id = $parentId;

        $modelConsulta->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        $modelConsulta->id_servicio = Yii::$app->user->getServicioActual();
        $modelConsulta->id_persona = $paciente->id_persona;
        $modelConsulta->id_efector = Yii::$app->user->getIdEfector();
        $modelConsulta->editando = 0;

       /* $log = sprintf(
            "Consulta:{URLAct: %s, URLSig: %s, URLAnt: %s}",
            $urlActual,
            $urlSiguiente,
            $urlAnterior
        );
        Yii::error($log);*/
    
        return ['success' => true, 'msg' => '', 'model' => $modelConsulta, 'modelEditando' => null];
        
        /*$parametrosExtra = '?id_servicio='.$idServicioRrhh.'&encounter_class='.$encounterClass;
        return [$idConfiguracion, $urlAnterior, $urlActual, $urlSiguiente, $parametrosExtra];*/
    }



    /**
     * Se sobreescribe este metodo para considerar que ConsultaAtencionesEnfermeria tiene relacion
     * con esta clase a través de parent
     */
    public function link($name, $model, $extraColumns = [])
    {
        if (($model instanceof ConsultaAtencionesEnfermeria) && !$model->isRelationPopulated('parentConsulta')) {
            $model->populateRelation('parentConsulta', $this);
        }

        parent::link($name, $model, $extraColumns);
    }

    /**
     * getParent hace referencia al vínculo con x clase,
     * se usan las propiedades parent_class y parent_id
     */    
    public function getParent()
    {
        if ($this->parent_id == 0) {
            return $this->hasOne(Turno::className(), ['id_turnos' => 'id_turnos']);
        }

        $parentIdAttr = 'id';
        switch ($this->parent_class) {
            case '\common\models\Turno':
            case '\common\models\ServiciosEfector':
                $parentIdAttr = 'id_turnos';
                break;
        }
        //TODO: revisar si se agrega otro parent ver la clase del hasOne
        return $this->hasOne(Turno::className(), [$parentIdAttr => 'parent_id']);
    }
    
    public static function returnMsjError($mensaje)
    {
        return [
            'success' => false, 
            'msg' => $mensaje, 
            'model' => null, 
            'modelEditando' => null
        ];
    }
    
    public function getHeader(){
        
        return ConsultasConfiguracion::getMenuPorIdConfiguracion($this->id_consulta,$this->id_configuracion, $this->paso_completado, $this->id_persona);
        
        //return '<nav class="nav"><a class="nav-link active" aria-current="page" href="#">Active</a>     <a class="nav-link" href="#">Link</a>     <a class="nav-link" href="#">Link</a>        <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Disabled</a>      </nav>';
    }

    public function validarPasosConfiguracionRequeridos(){

        $pasosRequeridosFaltantes = [];
        $relaciones = ConsultasConfiguracion::getRelacionesRequeridas($this->id_configuracion);
        foreach ($relaciones as  $value) {                                               
            $children = $this->$value;
            if (empty($children) ){ 
                $pasosRequeridosFaltantes [] = $value;
            }
        } 
        return $pasosRequeridosFaltantes;       
    }

    public function tienePasoUnico(){        
        $pasoUnico = ConsultasConfiguracion::checkPasoUnico($this->id_configuracion);
        
        return $pasoUnico;       
    }

    public function getCodEspecialidadSumar(){

        $id_servicio = $this->id_servicio;
        $profesionales = $this->rrhhEfector->persona->profesionalSalud;

        foreach($profesionales as $profesional){

            if($profesional->profesion->id_servicio == $id_servicio){

                return !$profesional->id_especialidad ? $profesional->profesion->codigo : $profesional->profesion->especialidades->conceptID;

            }
            
            if($profesional->profesion->nombre == 'Medico'){

                foreach($profesional->profesion->especialidades as $especialidad){
                    if($especialidad->id_servicio == $id_servicio){
                        return $especialidad->conceptID;
                    }
                }
            }
        }

        return null;

    }

    public static function existeConsultaPasePrevio($parent_id, $id_servicio){

        $consulta = self::find()
                    ->where(['parent_class' => self::PARENT_CLASSES[self::PARENT_PASE_PREVIO]])
                    ->andWhere(['parent_id' => $parent_id])
                    ->andWhere(['id_servicio' => $id_servicio])
                    ->one();

        return $consulta;

    }
    
    /*
     * Retorna True si es consulta de internación
     */
    public function esInternacion() {
        return (
        $this->parent_class == self::PARENT_CLASSES[self::PARENT_INTERNACION]
        );
    }

}
