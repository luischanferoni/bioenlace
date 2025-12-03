<?php

namespace common\models;

use Yii;

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
class AtencionesEnfermeria extends \yii\db\ActiveRecord
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
            [['id', 'id_persona', 'id_user'], 'integer'],
            [['datos', 'id_persona'], 'required'],
            [['datos', 'fecha_creacion'], 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'Id',
            'datos' => 'Datos',
            'id_persona' => 'Paciente',
            'id_user' => 'Usuario',
            'fecha_creacion' => 'Fecha Creacion',
        ];
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
        
    public function informeCantidadesMensuales($fecha_inicio) {     
        $id_efector =  Yii::$app->user->getIdEfector();
        $filtros = "fecha_creacion BETWEEN '$fecha_inicio' and LAST_DAY('$fecha_inicio')";
        $filtros .= " and deleted_at is null";
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
     return $command->queryAll();
    }
    
    /*
    * obtenerValoracionNutricional busca un registro en atenciones de enfermeria que se haya creado en 
    * la fecha de la consulta, para la persona que se esta atendiendo y en 
    * el efector en sesion. Ademas debe buscar que los parametros peso, 
    * talla, perimetro cefalico o tension arterial tengan valores en el 
    * campo datos 
    */
    public static function obtenerValoracionNutricional($fecha_consulta, $dato, $id_paciente) {

      /*  $id_efector =  Yii::$app->user->getIdEfector();
        $filtros = "fecha_creacion = '$fecha_consulta 00:00:00'";
        $filtros .= " and id_efector = $id_efector";
        $filtros .= " and (`datos` LIKE '-$dato%' OR `datos` LIKE '%271649006%')";
        
        $connection = Yii::$app->getDb();
        $command = $connection->createCommand("SELECT * FROM `atenciones_enfermeria` 
                WHERE id_persona = $id_paciente and $filtros");

        return $command->queryOne();*/
        // $valoracion = $command->queryOne();
        // return json_decode($valoracion['datos'], true);
        
        
        // TODO: Definir la necesidad de filtrar por datos
        $query = AtencionesEnfermeria::find()
            //->where(['like', 'datos', '%' . $dato . '%', false])
            ->andWhere(['fecha_creacion' => $fecha_consulta. ' 00:00:00'])
            ->andWhere(['id_efector' => Yii::$app->user->getIdEfector()])
            ->andWhere(['id_persona' => $id_paciente])
            ->orderBy(['id' => SORT_DESC])
            ->limit(1);

        //echo $query->createCommand()->getRawSql();die;
        return $query->one();
    }

    /*
    * porRecursohumano busca un registro creado por un recurso humano
    * TODO: definir si usar el id de recurso humano o el id de usuario univoco para todo el sistema
    */
    public static function porRecursohumano($id_rr_hh, $fecha_consulta) {
  
          return AtencionesEnfermeria::find()
            ->where(['fecha_creacion' => $fecha_consulta. ' 00:00:00'])
            ->where(['id_rr_hh' => $id_rr_hh])          
            ->one();
      }    

    /*
    * formatearDatos usado en la vista
    * @return string
    */
    function formatearDatos(){

        $valores = [];
        $datos = json_decode($this->datos, TRUE);
        if(!is_array($datos)) {
            return '';
        }        
        
        foreach ($datos as $key => $value) {
            switch ($key) {
                case 'sistolica':
                case '271649006':                    
                    $valores[0] = '<strong>Tensi&oacuten Arterial:</strong> '; 
                    $valores[0] .= $value.'/';
                    break;    
                case 'diastolica':  
                case '271650006':                    
                    if(isset($valores[0])){
                    $valores[0] .= $value; 
                    } else {
                    $valores[0] = $value;                         
                    }
                    break;
                case 'TensionArterial1':
                    $valores[0] = '<strong>Tensi&oacuten Arterial #1:</strong> ';
                    $valores[0] .= $value[271649006].'/'.$value[271650006].'<br/>';
                    break;
                case 'TensionArterial2':
                    $valores[0] .= '<strong>Tensi&oacuten Arterial #2:</strong> ';
                    $valores[0] .= $value[271649006].'/'.$value[271650006];
                    break;
                case 'peso':                                       
                case '162879003p':
                    $valores[1] = '<strong>Peso/Talla:</strong> '; 
                    $valores[1] .= 'P: '.$value.'kg. - '; 
                    break;
                case 'talla':  
                case '162879003t':                    
                    if(isset($valores[1])){
                    $valores[1] .= 'T: '.$value.'cm.';                                         
                    } else {
                    $valores[1] = 'T: '.$value.'cm.';                                                                 
                    }
                    break;
                case 'agudeza_ojo_izquierdo': 
                case '386708005': 
                    $valores[2] = '<strong>Agudeza Visual:</strong> ';
                    $valores[2] .= 'OI: '.$value.' - ';
                    break;
                case 'agudeza_ojo_derecho':
                case '386709002': 
                    $valores[2] .= 'OD: '.$value;
                    break;
                case 'temperatura':
                case '703421000':                    
                    $valores[3] = '<strong>Temperatura:</strong> ';
                    $valores[3] .= $value. 'º';
                    break;
                case 'glucemia_capilar':
                case '434912009':                    
                    $valores[4] = '<strong>Glucemia Capilar:</strong> ';
                    $valores[4] .= $value;
                    break;
                case 'circunferencia_abdominal':
                case '396552003':                    
                    $valores[5] = '<strong>Circunferencia Abdominal:</strong> ';
                    $valores[5] .= $value.'cm.';
                    break;
                case 'perimetro_cefalico':
                case '363812007':  
                    $valores[6] = '<strong>Perimetro Cefálico:</strong> ';
                    $valores[6] .= $value. 'cm.';
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
                default:
                    break;
            }
        }
        if (count($valores) == 0) {
            $valores[0] = 'Sin datos: '; 
            $valores[0] .= '/'; 
        }      

        return implode($valores, '<br/>');
    }

    public function beforeSave($insert) {
         
        if(parent::beforeSave($insert)) {
            if (!isset($this->fecha_creacion)){
                $this->fecha_creacion = date("Y-m-d");
            }
            $this->id_user = Yii::$app->user->id;            
            //$this->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
            $this->id_efector = Yii::$app->user->getIdEfector();
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
