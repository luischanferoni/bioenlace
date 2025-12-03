<?php

namespace common\models;

use Yii;
use yii\validators\NumberValidator;

/**
 * This is the model class for table "atenciones_enfermeria".
 *
 * @property integer $id
 * @property string $datos 
 * @property string $fecha_creacion

 * @property string $peso
 * @property string $per_peso
 * @property string $talla
 * @property string $per_talla
 * @property string $perim_cefalico
 * @property string $per_perim_cefalico
 * @property string $imc
 * @property string $per_imc
 * @property string $id_consulta
 *
 * @property Rrhh $id_user
 * @property Personas $id_persona
 */
class ConsultaAtencionesEnfermeria extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'atenciones_enfermeria';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
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
            [['fecha_creacion', 'hora_creacion'], 'safe'],
            [['id_consulta', 'id_persona', 'id_user', 'id_rr_hh', 'id_rrhh_servicio'], 'integer'],
            [['datos', 'id_consulta'], 'required'],
            [['datos'], 'validarDatos'],
            [['id_rrhh_servicio'], 'default', 'value' => 0],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            "Datos",
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'Id',
            'id_consulta' => 'id_consulta',
            'datos' => 'Datos',
            'id_persona' => 'Paciente',
            'id_user' => 'Usuario',
            'fecha_creacion' => '',
            'hora_creacion' => ''
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(Persona::className(), ['id_user' => 'id_user']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }

    public function getRrhhEfector()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    /**
     * getParent hace referencia al vínculo con x clase,
     * se usan las propiedades parent y parent_id
     */
    public function getParentConsulta()
    {
        return $this->hasOne($this->parent_class, ['id_consulta' => 'parent_id']);
    }

    /**
     * getParent hace referencia al vínculo con x clase,
     * se usan las propiedades parent y parent_id
     */
    public function getParent()
    {
        return $this->hasOne($this->parent_class, ['id' => 'parent_id']);
    }

    public function informeCantidadesMensuales($fecha_inicio)
    {
        $id_efector =  Yii::$app->user->getIdEfector();
        $filtros = "fecha_creacion BETWEEN '$fecha_inicio' and LAST_DAY('$fecha_inicio')";
        $filtros .= " and id_efector = $id_efector";
        $connection = Yii::$app->getDb();
        $command = $connection->createCommand("select 
           COUNT(DISTINCT fecha_creacion) as cant_dias,
	   COUNT(DISTINCT id_persona) as total,        
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE (`datos` like '%sistolica%' OR `datos` like '%271649006%') and (datos like '%diastolica%' OR datos like '%271650006%') and $filtros) as 'TA',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE (`datos` like '%peso%' OR `datos` like '%162879003p%') and (`datos` like '%talla%' OR  `datos` like '%162879003t%') and $filtros) AS 'PT',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE (`datos` like '%perimetro_cefalico%' OR `datos` like '%363812007%') and $filtros) as 'per',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE (`datos` like '%temperatura%' OR `datos` like '%703421000%') and $filtros) as 'temp',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE (`datos` like '%glucemia_capilar%' OR `datos` like '%434912009%') and $filtros) AS 'GC',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE (`datos` like '%circunferencia_abdominal%' OR `datos` like '%396552003%') and $filtros) AS 'CA',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE ((`datos` like '%agudeza_ojo_derecho%' and `datos` like '%agudeza_ojo_izquierdo%') OR (`datos` like '%386708005%' and `datos` like '%386708005%')) and $filtros) AS 'AV',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` LIKE '%rescate_sbo%' and $filtros) AS 'RS',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` like '%inyectable%' and $filtros) AS 'iny',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` like '%curacion%' and $filtros) AS 'cur',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` like '%visita_domiciliaria%' and $filtros) AS 'VD',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` like '%nebulizacion%' and $filtros) AS 'neb',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` like '%inmunizacion%' and $filtros) AS 'inm',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` like '%electrocardiograma%' and $filtros) as 'ECG',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` like '%extraccion_puntos%' and $filtros) as 'EP',
      (SELECT COUNT(*) FROM `atenciones_enfermeria` WHERE `datos` like '%internacion_abreviada%' and $filtros) as 'IA'
      from atenciones_enfermeria WHERE $filtros");
        return $atenciones = $command->queryAll();
    }

    /*
    * obtenerValoracionNutricional busca un registro en atenciones de enfermeria que se haya creado en 
    * la fecha de la consulta, para la persona que se esta atendiendo y en 
    * el efector en sesion. Ademas debe buscar que los parametros peso, 
    * talla, perimetro cefalico o tension arterial tengan valores en el 
    * campo datos 
    */
    public static function obtenerValoracionNutricional($fecha_consulta, $dato, $id_paciente)
    {

        // TODO: Definir la necesidad de filtrar por datos
        $query = ConsultaAtencionesEnfermeria::find()
            //->where(['like', 'datos', '%' . $dato . '%', false])
            ->andWhere(['fecha_creacion' => $fecha_consulta . ' 00:00:00'])
            ->andWhere(['id_efector' => Yii::$app->user->getIdEfector()])
            ->andWhere(['id_persona' => $id_paciente])
            ->orderBy(['id' => SORT_DESC])
            ->limit(1);

        //echo $query->createCommand()->getRawSql();die;
        return $query->one();
    }


    public static function obtenerUltimaAtencionPorPaciente($id_paciente)
    {

        // TODO: Definir la necesidad de filtrar por datos
        $query = ConsultaAtencionesEnfermeria::find()
            //->where(['like', 'datos', '%' . $dato . '%', false])
            ->andWhere(['id_efector' => Yii::$app->user->getIdEfector()])
            ->andWhere(['id_persona' => $id_paciente])
            ->orderBy(['id' => SORT_DESC])
            ->limit(1);

        return $query->one();
    }

    public static function obtenerValoracionNutricionalPorIdConsulta($idConsulta)
    {
        $query = ConsultaAtencionesEnfermeria::find()
            ->andWhere(['id_consulta' => $idConsulta])
            ->limit(1);

        return $query->one();
    }

    /*
    * @param string|int $id_persona
    * @return yii\db\ActiveRecord|null
    */
    public static function ultimoPorPaciente($id_persona)
    {
        $query = ConsultaAtencionesEnfermeria::find()
            ->andWhere(['id_persona' => $id_persona])
            ->orderBy(['id' => SORT_DESC])
            ->limit(1);

        return $query->one();
    }

    /*
    * porRecursohumano busca un registro creado por un recurso humano
    * TODO: definir si usar el id de recurso humano o el id de usuario univoco para todo el sistema
    */
    public static function porRecursohumano($id_rr_hh, $fecha_consulta)
    {

        return ConsultaAtencionesEnfermeria::find()
            ->where(['fecha_creacion' => $fecha_consulta . ' 00:00:00'])
            ->where(['id_rr_hh' => $id_rr_hh])
            ->one();
    }

    /*
    * formatearDatos usado en la vista
    * @return string
    */
    function formatearDatos()
    {

        $valores = [];
        $datos = json_decode($this->datos, TRUE);
        if (!is_array($datos)) {
            return '';
        }

        foreach ($datos as $key => $value) {
            switch ($key) {
                case 'sistolica':
                case '271649006':
                    $valores[0] = '<strong>Tensi&oacuten Arterial:</strong> ';
                    $valores[0] .= $value . '/';
                    break;
                case 'diastolica':
                case '271650006':
                    if (isset($valores[0])) {
                        $valores[0] .= $value;
                    } else {
                        $valores[0] = $value;
                    }
                    break;
                case 'TensionArterial1':
                    $valores[0] = '<strong>Tensi&oacuten Arterial #1:</strong> ';
                    $valores[0] .= $value[271649006] . '/' . $value[271650006] . '<br/>';
                    break;
                case 'TensionArterial2':
                    if (isset($valores[0])) {
                        $valores[0] .= '<strong>Tensi&oacuten Arterial #2:</strong> ';
                    } else {
                        $valores[0] = '<strong>Tensi&oacuten Arterial #2:</strong> ';
                    }

                    $valores[0] .= $value[271649006] . '/' . $value[271650006];
                    break;
                case 'peso':
                case '162879003p':
                    $valores[1] = '<strong>Peso/Talla:</strong> ';
                    $valores[1] .= 'P: ' . $value . 'kg. - ';
                    break;
                case 'talla':
                case '162879003t':
                    if (isset($valores[1])) {
                        $valores[1] .= 'T: ' . $value . 'cm.';
                    } else {
                        $valores[1] = 'T: ' . $value . 'cm.';
                    }
                    break;
                case 'agudeza_ojo_izquierdo':
                case '386708005':
                    $valores[2] = '<strong>Agudeza Visual:</strong> ';
                    $valores[2] .= 'OI: ' . $value . '/10 - ';
                    break;
                case 'agudeza_ojo_derecho':
                case '386709002':
                    $valores[2] .= 'OD: ' . $value . '/10';
                    break;
                case 'temperatura':
                case '703421000':
                    $valores[3] = '<strong>Temperatura:</strong> ';
                    $valores[3] .= $value . 'º';
                    break;
                case 'glucemia_capilar':
                case '434912009':
                    $valores[4] = '<strong>Glucemia Capilar:</strong> ';
                    $valores[4] .= $value;
                    break;
                case 'circunferencia_abdominal':
                case '396552003':
                    $valores[5] = '<strong>Circunferencia Abdominal:</strong> ';
                    $valores[5] .= $value . 'cm.';
                    break;
                case 'perimetro_cefalico':
                case '363812007':
                    $valores[6] = '<strong>Perimetro Cefálico:</strong> ';
                    $valores[6] .= $value . 'cm.';
                    break;
                case 'campaña':
                    $valores[7] = '<strong>Campaña:</strong> ';
                    $valores[7] .= 'SI';
                    break;

                case 'nebulizacion':
                    $valores[7] = '<strong>Nebulización:</strong> ';
                    $valores[7] .= 'SI';
                    break;
                case 'rescate_sbo':
                    $valores[8] = '<strong>Rescate y SBO:</strong> ';
                    $valores[8] .= 'SI';
                    break;
                case 'inyectable':
                    $valores[9] = '<strong>Inyectable:</strong> ';
                    $valores[9] .= 'SI';
                    break;
                case 'inmunizacion':
                    $valores[10] = '<strong>Inmunización:</strong> ';
                    $valores[10] .= 'SI';
                    break;
                case 'extraccion_puntos':
                    $valores[11] = '<strong>Extracción Puntos:</strong> ';
                    $valores[11] .= 'SI';
                    break;
                case 'curacion':
                    $valores[12] = '<strong>Curación:</strong> ';
                    $valores[12] .= 'SI';
                    break;
                case 'internacion_abreviada':
                    $valores[13] = '<strong>Internacion Abreviada:</strong> ';
                    $valores[13] .= 'SI';
                    break;
                case 'visita_domiciliaria':
                    $valores[14] = '<strong>Visita Domiciliaria:</strong> ';
                    $valores[14] .= 'SI';
                    break;
                case 'electrocardiograma':
                    $valores[15] = '<strong>Electrocardiograma:</strong> ';
                    $valores[15] .= 'SI';
                    break;
                case '364075005': //Frecuencia Cardíaca
                    $valores[16] = '<strong>Frecuencia Cardíaca:</strong> ';
                    $valores[16] .= $value;
                    break;
                case '86290005': //Frecuencia Respiratioria
                    $valores[17] = '<strong>Frecuencia Respiratioria:</strong> ';
                    $valores[17] .= $value;
                    break;
                case '103228002': //Saturación de Oxigeno
                    $valores[18] = '<strong>Saturación de Oxígeno:</strong> ';
                    $valores[18] .= $value;
                    break;
                default:
                    //[225965002]=>  string(16) "cambio_ropa_cama"
                    $valores[] = ucfirst(str_replace("_", " ", $value));

                    break;
            }
        }
        if (count($valores) == 0) {
            $valores[0] = 'Sin datos: ';
            $valores[0] .= '/';
        }

        return implode('<br/>', $valores);
    }


    public function validarDatos($attribute, $params)
    {

        $datos = json_decode($this->$attribute, TRUE);

        $persona = Persona::findOne($this->id_persona);
        $edadPersona = $persona->edad;
        $esInternacion = $this->consulta->parent_class == Consulta::PARENT_CLASSES[Consulta::PARENT_INTERNACION];

        if ($datos != "") {
            foreach ($datos as $key => $value) {
                switch ($key) {

                    case 'TensionArterial1':

                        if (isset($datos['TensionArterial2']) || $esInternacion) {

                            if (isset($value[271649006]) && isset($value[271650006])) {
                                $tensionS_validator = new NumberValidator();
                                $tensionS_validator->min = 50;
                                $tensionS_validator->max = 300;
                                $tensionS_validator->message = 'La tension sistolica debe ser un numero';
                                $tensionS_validator->tooBig = 'La tension sistolica no puede ser mayor a 300.';
                                $tensionS_validator->tooSmall = 'La tension sistolica no puede ser menor a 50';

                                if (!$tensionS_validator->validate($value[271649006], $error)) {
                                    $this->addError($attribute, $error);
                                }

                                $tensionD_validator = new NumberValidator();
                                $tensionD_validator->min = 40;
                                $tensionD_validator->max = 150;
                                $tensionD_validator->message = 'La tension diastolica debe ser un numero';
                                $tensionD_validator->tooBig = 'La tension diastolica no puede ser mayor a 150.';
                                $tensionD_validator->tooSmall = 'La tension diastolica no puede ser menor a 40';

                                if (!$tensionD_validator->validate($value[271650006], $error)) {
                                    $this->addError($attribute, $error);
                                }
                            } else {
                                $this->addError($attribute, 'Control TA 1: Se debe cargar tanto la tension sistolica como la diastolica');
                            }
                        } else {
                                $this->addError($attribute, 'Se debe realizar la carga de los dos controles de tension');
                        }

                        break;

                    case 'TensionArterial2':
                        if (isset($datos['TensionArterial1']) || $esInternacion) {
                            if (isset($value[271649006]) && isset($value[271650006])) {
                                $tensionS_validator = new NumberValidator();
                                $tensionS_validator->min = 50;
                                $tensionS_validator->max = 300;
                                $tensionS_validator->message = 'La tension sistolica debe ser un numero';
                                $tensionS_validator->tooBig = 'La tension sistolica no puede ser mayor a 300.';
                                $tensionS_validator->tooSmall = 'La tension sistolica no puede ser menor a 50';

                                if (!$tensionS_validator->validate($value[271649006], $error)) {
                                    $this->addError($attribute, $error);
                                }

                                $tensionD_validator = new NumberValidator();
                                $tensionD_validator->min = 40;
                                $tensionD_validator->max = 150;
                                $tensionD_validator->message = 'La tension diastolica debe ser un numero';
                                $tensionD_validator->tooBig = 'La tension diastolica no puede ser mayor a 150.';
                                $tensionD_validator->tooSmall = 'La tension diastolica no puede ser menor a 40';

                                if (!$tensionD_validator->validate($value[271650006], $error)) {
                                    $this->addError($attribute, $error);
                                }
                            } else {
                                $this->addError($attribute, 'Control TA 2: Se debe cargar tanto la tension sistolica como la diastolica');
                            }
                        } else {
                                $this->addError($attribute, 'Se debe realizar la carga de los dos controles de tension');
                        }

                        break;

                    case 'peso':
                    case '162879003p':

                        $peso_validator = new NumberValidator();
                        $peso_validator->message = 'Peso debe ser un numero expresado en Kg.';



                        if ($edadPersona < 10) {
                            $peso_validator->min = 0.5;
                            $peso_validator->max = 250;
                            $peso_validator->tooBig = 'El peso en Kg. no puede ser mayor a 250.';
                            $peso_validator->tooSmall = 'El peso en Kg. no puede ser menor a 0.5';

                            if (!$peso_validator->validate($value, $error)) {
                                $this->addError($attribute, $error);
                            }
                        } else if ($edadPersona < 20) {
                            $peso_validator->min = 15;
                            $peso_validator->max = 250;
                            $peso_validator->tooBig = 'El peso en Kg. no puede ser mayor a 250.';
                            $peso_validator->tooSmall = 'El peso en Kg. no puede ser menor a 15';

                            if (!$peso_validator->validate($value, $error)) {
                                $this->addError($attribute, $error);
                            }
                        } else if ($edadPersona >= 20) {
                            $peso_validator->min = 30;
                            $peso_validator->max = 250;
                            $peso_validator->tooBig = 'El peso en Kg. no puede ser mayor a 250.';
                            $peso_validator->tooSmall = 'El peso en Kg. no puede ser menor a 40';

                            if (!$peso_validator->validate($value, $error)) {
                                $this->addError($attribute, $error);
                            }
                        }

                        break;

                    case 'talla':
                    case '162879003t':

                        $talla_validator = new NumberValidator();
                        $talla_validator->message = 'Talla debe ser un numero expresado en cm.';

                        if ($edadPersona < 10) {
                            $talla_validator->min = 30;
                            $talla_validator->max = 180;
                            $talla_validator->tooBig = 'La talla en cm no puede ser mayor a 180.';
                            $talla_validator->tooSmall = 'La talla en cm no puede ser menor a 40';

                            if (!$talla_validator->validate($value, $error)) {
                                $this->addError($attribute, $error);
                            }
                        } else if ($edadPersona >= 10) {
                            $talla_validator->min = 80;
                            $talla_validator->max = 220;
                            $talla_validator->tooBig = 'La talla en cm no puede ser mayor a 220.';
                            $talla_validator->tooSmall = 'La talla en cm no puede ser menor a 80';

                            if (!$talla_validator->validate($value, $error)) {
                                $this->addError($attribute, $error);
                            }
                        }
                        break;

                    case 'agudeza_ojo_izquierdo':
                    case '386708005':

                        $agudeza_ojoI_validator = new NumberValidator();
                        $agudeza_ojoI_validator->message = 'La agudeza visual del ojo izquierdo tiene que ser un numero';
                        $agudeza_ojoI_validator->min = 0;
                        $agudeza_ojoI_validator->max = 10;
                        $agudeza_ojoI_validator->tooBig = 'La agudeza visual del ojo izquierdo no puede ser mayor a 10';
                        $agudeza_ojoI_validator->tooSmall = 'La agudeza visual del ojo izquierdo no puede ser menor a 0';

                        if (!$agudeza_ojoI_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }
                        break;

                    case 'agudeza_ojo_derecho':
                    case '386709002':

                        $agudeza_ojoD_validator = new NumberValidator();
                        $agudeza_ojoD_validator->message = 'La agudeza visual del ojo derecho tiene que ser un numero';
                        $agudeza_ojoD_validator->min = 0;
                        $agudeza_ojoD_validator->max = 10;
                        $agudeza_ojoD_validator->tooBig = 'La agudeza visual del ojo derecho no puede ser mayor a 10';
                        $agudeza_ojoD_validator->tooSmall = 'La agudeza visual del ojo derecho no puede ser menor a 0';

                        if (!$agudeza_ojoD_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }
                        break;

                    case 'temperatura':
                    case '703421000':
                        $temperatura_validator = new NumberValidator();
                        $temperatura_validator->message = 'La temperatura tiene que ser un numero';
                        $temperatura_validator->min = 30;
                        $temperatura_validator->max = 45;
                        $temperatura_validator->tooBig = 'La temperatura no puede ser mayor a 45°';
                        $temperatura_validator->tooSmall = 'La temperatura no puede ser menor a 30°';

                        if (!$temperatura_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }
                        break;

                    case 'perimetro_cefalico':
                    case '363812007':
                        $perimetro_cefalico_validator = new NumberValidator();
                        $perimetro_cefalico_validator->message = 'El perimetro cefalico tiene que ser un numero';
                        $perimetro_cefalico_validator->min = 29;
                        $perimetro_cefalico_validator->max = 54;
                        $perimetro_cefalico_validator->tooBig = 'El perimetro cefalico no puede ser mayor a 54 cm.';
                        $perimetro_cefalico_validator->tooSmall = 'El perimetro cefalico no puede ser menor a 29 cm.';

                        if (!$perimetro_cefalico_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }


                    case 'glucemia_capilar':
                    case '434912009':

                        $glucemia_capilar_validator = new NumberValidator();
                        $glucemia_capilar_validator->message = 'La glucemia capilar tiene que ser un numero';
                        //$glucemia_capilar_validator->min = 29;
                        //$glucemia_capilar_validator->max = 54;
                        //$glucemia_capilar_validator->tooBig = 'El perimetro cefalico no puede ser mayor a 54 cm.';
                        //$glucemia_capilar_validator->tooSmall = 'El perimetro cefalico no puede ser menor a 29 cm.';

                        if (!$glucemia_capilar_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }
                        break;

                    case 'circunferencia_abdominal':
                    case '396552003':

                        $circunferencia_abdominal_validator = new NumberValidator();
                        $circunferencia_abdominal_validator->message = 'La circunferencia abdominal tiene que ser un numero';
                        //$circunferencia_abdominal_validator->min = 29;
                        //$circunferencia_abdominal_validator->max = 54;
                        //$circunferencia_abdominal_validator->tooBig = 'La circunferencia abdominal no puede ser mayor a 54 cm.';
                        //$circunferencia_abdominal_validator->tooSmall = 'La circunferencia abdominal no puede ser menor a 29 cm.';

                        if (!$circunferencia_abdominal_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }
                        break;

                    case '364075005':
                        $frecuencia_cardiaca_validator = new NumberValidator();
                        $frecuencia_cardiaca_validator->message = 'La frecuencia cardiaca tiene que ser un numero';
                        $frecuencia_cardiaca_validator->min = 0;
                        $frecuencia_cardiaca_validator->max = 300;
                        $frecuencia_cardiaca_validator->tooBig = 'La frecuencia cardiaca no puede ser mayor a 300';
                        $frecuencia_cardiaca_validator->tooSmall = 'La frecuencia cardiaca no puede ser menor a 0';

                        if (!$frecuencia_cardiaca_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }
                        break;

                    case '86290005':
                        $frecuencia_respiratoria_validator = new NumberValidator();
                        $frecuencia_respiratoria_validator->message = 'La frecuencia respiratoria tiene que ser un numero';
                        $frecuencia_respiratoria_validator->min = 1;
                        $frecuencia_respiratoria_validator->max = 60;
                        $frecuencia_respiratoria_validator->tooBig = 'La frecuencia respiratoria no puede ser mayor a 60';
                        $frecuencia_respiratoria_validator->tooSmall = 'La frecuencia respiratoria no puede ser menor a 1';

                        if (!$frecuencia_respiratoria_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }

                        break;
                    case '103228002':
                        $saturacion_oxigeno_validator = new NumberValidator();
                        $saturacion_oxigeno_validator->message = 'La saturacion de oxigeno tiene que ser un numero';
                        $saturacion_oxigeno_validator->min = 0;
                        $saturacion_oxigeno_validator->max = 99;
                        $saturacion_oxigeno_validator->tooBig = 'La saturacion de oxigeno no puede ser mayor a 99';
                        $saturacion_oxigeno_validator->tooSmall = 'La saturacion de oxigeno no puede ser menor a 0';

                        if (!$saturacion_oxigeno_validator->validate($value, $error)) {
                            $this->addError($attribute, $error);
                        }

                        break;
                    default:
                        break;
                }
            }
        } else {
            if ($this->observaciones != '') {
                return true;
            } else {
                $this->addError($this->observaciones, 'La carga de atencion enfermeria no puede ser vacia.');
            }
        }
    }


    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {

            if (date_create_from_format('d/m/Y', $this->fecha_creacion)) {
                $fecha = date_create_from_format('d/m/Y', $this->fecha_creacion);
                $fechaFormateada = date_format($fecha, 'Y-m-d');
                $this->fecha_creacion = $fechaFormateada;
            }


            //para la migracion comentar el id_user y poner el id_efector en 0
            $this->id_user = Yii::$app->user->id;
            $this->id_efector = Yii::$app->user->getIdEfector();;
        }

        if ($this->isRelationPopulated('parent')) {
            $this->parent_class = get_class($this->parent);
        }

        if ($this->isRelationPopulated('parentConsulta')) {
            $this->parent_class = get_class($this->parentConsulta);
        }

        return true;
    }
}
