<?php

namespace common\models;
use common\models\snomed\SnomedProcedimientos;
use yii\data\ActiveDataProvider;
use common\models\Consulta;
use Yii;

/**
 * This is the model class for table "consultas_practicas".
 *
 * @property string $id_practicas_personas
 * @property integer $id_persona
 * @property string $ id_detalle_practicas
 * @property string $fecha
 *
 * @property Personas $idPersona
 */
class ConsultaDerivaciones extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public $select2_codigo;
    
    const ESTADO_EN_ESPERA = 'EN_ESPERA';
    const ESTADO_CON_TURNO = 'CON_TURNO';
    const ESTADO_RECHAZADA = 'RECHAZADA';
    const ESTADO_RESUELTA = 'RESUELTA';

    const PRACTICA = 'PRACTICA';
    const INTERCONSULTA = 'INTERCONSULTA';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consultas_derivaciones';
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_servicio', 'id_consulta_solicitante', 'id_efector'], 'required'],
            ['select2_codigo', 'each', 'rule' => ['string']],
            [['id_rr_hh'], 'integer'],
            [['tipo', 'indicaciones', 'codigo', 'tipo_solicitud'], 'string'],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            "Servicio",
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_servicio' => 'Servicio',
            'id_efector' => 'Efector',
            'id_practicas_personas' => 'Id Practicas Personas',
            'tipo' => 'Tipo',
            'codigo' => 'Código',
        ];
    }
    
     /**
     * @return \yii\db\ActiveQuery
     */
    public function getServicio()
    {
        return $this->hasOne(Servicio::className(), ['id_servicio' => 'id_servicio']);
    }

     /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public static function getDerivacionesPorPersona($id_persona, $id_efector, $id_servicio, $estado)
    {
        return self::find()
        #echo self::find()
        ->join('INNER JOIN','consultas', '`consultas`.`id_consulta` = `consultas_derivaciones`.`id_consulta_solicitante`')
        ->join('INNER JOIN','personas', '`personas`.`id_persona` = `consultas`.`id_persona`')
        ->where('consultas_derivaciones.estado = :estado')
        ->andWhere('consultas_derivaciones.id_efector = :id_efector')
        ->andWhere('consultas_derivaciones.id_servicio = :id_servicio')
        ->andWhere('personas.id_persona = :id_persona')
        ->addParams([':estado'=>$estado,':id_efector'=>$id_efector,':id_servicio'=>$id_servicio,':id_persona'=>$id_persona])
        #->createCommand()->getRawSql();exit;
        ->all();
    }


    public static function getDerivacionesRechazadaPorPersona($id_consulta, $id_persona, $id_efector, $id_servicio, $estado)
    {
        return self::find()
            #echo self::find()
            ->join('INNER JOIN','consultas', '`consultas`.`id_consulta` = `consultas_derivaciones`.`id_consulta_solicitante`')
            ->join('INNER JOIN','personas', '`personas`.`id_persona` = `consultas`.`id_persona`')
            ->where('consultas_derivaciones.estado = :estado')
            ->andWhere('consultas_derivaciones.id_efector = :id_efector')
            ->andWhere('consultas_derivaciones.id_servicio = :id_servicio')
            ->andWhere('consultas_derivaciones.id_respondido = :id_consulta')
            ->andWhere('personas.id_persona = :id_persona')
            ->addParams([':id_consulta'=>$id_consulta,':estado'=>$estado,':id_efector'=>$id_efector,':id_servicio'=>$id_servicio,':id_persona'=>$id_persona])
            #->createCommand()->getRawSql();exit;
            ->all();
    }

     /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta_solicitante']);
    }

    
    //Busca los detalles practicas por consulta
    public function getPracticasSolicitadasPorConsulta($id_cons)
    {
       $practicas_persona = self::findAll(['id_consulta_solicitante' => $id_cons]);
       return $practicas_persona;
               
    }

    //Busca consulta parcticas solicitadas de un turno por referencia
    public static function getPracticaSolicitadasPorIdConsultaSolicitada($id_consulta_solicitada)
    {
        $consulta_practica_solicitadas = self::findOne(['id_consulta_solicitante' => $id_consulta_solicitada]);
        return $consulta_practica_solicitadas;

    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodigoSnomed()
    {
        return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'codigo']);
    }

    /**
     * Gets query for [[RrHh]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhDerivado()
    {
        return $this->hasOne(Rrhh::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    /**
     * Mientras la consulta no este finalizada (nueva o editando) el usuario
     * puede hacer un hard delete
     */
    public static function hardDeleteGrupo($id_consulta, $ids)
    {
        if (count($ids) > 0 && isset($id_consulta) && $id_consulta != "" && $id_consulta != 0) {
            self::hardDeleteAll([
                'AND',
                ['in', 'id', $ids],
                ['=', 'id_consulta_solicitante', $id_consulta]
            ]);
        }
    }    

    /**
     * Creates data provider for consultas_practicas_solicitadas
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function porEfectorPorServicio($id_efector, $id_servicio)
    {

        if($id_servicio):
            $query = ConsultaDerivaciones::find()
                ->select('*')
                ->where('id_efector = '.$id_efector)
                ->andWhere('id_servicio = '.$id_servicio)
                ->andWhere('estado = "EN_ESPERA"');
        else:
            $query = ConsultaDerivaciones::find()
                ->select('*')
                ->where('id_efector = '.$id_efector)
                ->andWhere('estado = "EN_ESPERA"');
        endif;


        $dataProvider = new ActiveDataProvider([
                    'query' => $query,
                    ]);
        
        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

    }

    /**
     * Creates data provider for consultas_practicas_solicitadas
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function porReferencia($id_efector)
    {
        return ConsultaDerivaciones::find()
                ->select('*')
                ->where('id_efector = '.$id_efector)
                ->andWhere('estado = "EN_ESPERA"')            
                ->orderBy('id_servicio')
                ->all();
    }

    /**
     * Derivaciones activas existentes para una persona, en ciertos servicios
     */
    public static function getDerivacionesActivasPorPacientePorServiciosPorEfector($idPaciente, $idsServicios, $idEfector)
    {
        return self::find()                                
                ->andWhere('consultas_derivaciones.estado = "'.self::ESTADO_EN_ESPERA.'"')
                ->andWhere('consultas_derivaciones.id_efector = '.$idEfector)
                ->andWhere(['in', 'consultas_derivaciones.id_servicio', $idsServicios])
                ->join('INNER JOIN', 'consultas', 'id_consulta_solicitante = consultas.id_consulta AND consultas.id_persona = ' . $idPaciente)
                ->all();
    }    
}
