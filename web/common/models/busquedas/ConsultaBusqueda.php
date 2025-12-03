<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Consulta;

/**
 * ConsultaBusqueda represents the model behind the search form about `common\models\Consulta`.
 */
class ConsultaBusqueda extends Consulta
{
    public $conAutofacturacion = false;
    public $listadoConsultasEnviadas = false;
    public $fecha_desde = null;
    public $fecha_hasta = null;
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_consulta', 'id_turnos', 'id_efector'], 'integer'],
            [['hora', 'consulta_inicial', 'motivo_consulta', 'observacion', 'control_embarazo'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }
    public function search_sumar($params)
    {
        //variable con el usuario que inicio sesion
        $id_user = Yii::$app->user->id;
        $id_efector = Yii::$app->user->getIdEfector();
        //$id_efector = Yii::$app->user->idEfector;
//        $id_user = 4;
//        $id_efector=10;
        
        $expression = new \yii\db\Expression('NOW()');
        $fecha_hora = (new \yii\db\Query)->select($expression)->scalar();
        list($fecha_hoy,$hora) = explode(" ", $fecha_hora);
        
        //$query = Consulta::find();
        $query = Consulta::find()
                        ->select('consultas.*')
                        ->leftJoin('turnos', '`turnos`.`id_turnos` = `consultas`.`id_turnos`')
                        ->leftJoin('rr_hh', '`turnos`.`id_rr_hh` = `rr_hh`.`id_rr_hh`')
                        ->leftJoin('personas', '`rr_hh`.`id_persona` = `personas`.`id_persona`')
                        ->andWhere('turnos.fecha = :fecha_hoy',[':fecha_hoy' => $fecha_hoy])
                        ->andWhere('turnos.id_efector = :id_efector',[':id_efector' => $id_efector])
                        ->orderBy('turnos.fecha DESC,turnos.hora DESC');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id_consulta' => $this->id_consulta,
            'id_turnos' => $this->id_turnos,
            'hora' => $this->hora,
            'id_tipo_consulta' => $this->id_tipo_consulta,
        ]);

        $query->andFilterWhere(['like', 'consulta_inicial', $this->consulta_inicial])
            ->andFilterWhere(['like', 'motivo_consulta', $this->motivo_consulta])
            ->andFilterWhere(['like', 'observacion', $this->observacion])
            ->andFilterWhere(['like', 'control_embarazo', $this->control_embarazo]);

        return $dataProvider;
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        //variable con el usuario que inicio sesion
        $id_user = Yii::$app->user->id;
        // TODO este parametro y el resto debería de setearse antes de la llamada a este metodo
        // deberia de venir en $params
        
        //$query = Consulta::find();

        // No quiero hacer un lazy loading y lanzar queries por cada reqistro en un loop
        // entonces prefiero traer todas las relaciones 1 a 1 de entrada
        $query = Consulta::find()
                        ->select('consultas.*')                        
                        //->leftJoin('rr_hh')
                        ->with('paciente')
                        //->leftJoin('rr_hh_efector', '`rr_hh_efector`.`id_rr_hh` = `rr_hh`.`id_rr_hh`')
                        //->where(['personas.id_user' => $id_user])
                        //->andWhere(['=', '`turnos`.`fecha`', date('Y-m-d')])
                        ->andWhere('id_efector = :id_efector',[':id_efector' => $this->id_efector])
                        ->orderBy('created_at DESC');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id_consulta' => $this->id_consulta,
            'id_turnos' => $this->id_turnos,
            'hora' => $this->hora,
            'id_tipo_consulta' => $this->id_tipo_consulta,
        ]);

        $query->andFilterWhere(['like', 'consulta_inicial', $this->consulta_inicial])
            ->andFilterWhere(['like', 'motivo_consulta', $this->motivo_consulta])
            ->andFilterWhere(['like', 'observacion', $this->observacion])
            ->andFilterWhere(['like', 'control_embarazo', $this->control_embarazo]);

        if (!isset($this->conAutofacturacion)) {
            return $dataProvider;
        }

        if ($this->conAutofacturacion) {
            $query->joinWith(['autofacturacion' => function($q) {
                $q->andWhere(['sumar_autofacturacion.fecha_envio'=>NULL]);
                $q->andWhere('sumar_autofacturacion.id_consulta IS NOT NULL');

                $q->where(['IS NOT','sumar_autofacturacion.respuesta_sumar',NULL])
                ->andFilterWhere(['>=','sumar_autofacturacion.fecha_envio',$this->fecha_desde])
                ->andFilterWhere(['<=','sumar_autofacturacion.fecha_envio',$this->fecha_hasta]);
               
            }]);

            if ($this->listadoConsultasEnviadas) {
                $query->joinWith(['autofacturacion' => function($q) {
    
                    if(isset($this->fecha_desde) && isset($this->fecha_hasta)){
                        $q->where(['IS NOT','sumar_autofacturacion.respuesta_sumar',NULL])
                          ->andFilterWhere(['>=','sumar_autofacturacion.fecha_envio',$this->fecha_desde])
                          ->andFilterWhere(['<=','sumar_autofacturacion.fecha_envio',$this->fecha_hasta]);
    
                    }else{
    
                        $q->where(['IS NOT','sumar_autofacturacion.respuesta_sumar',NULL]);
                        
                    }
                    //echo $q->createCommand()->getRawSql();die;
                }]);
            }
        } else {
            $query->joinWith(['autofacturacion' => function($q) {
                $q->andWhere('sumar_autofacturacion.id_consulta IS NULL');               
            }]);
        }

        return $dataProvider;
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchGral($params)
    {
        //variable con el usuario que inicio sesion
        $id_user = Yii::$app->user->id;
        $id_efector = Yii::$app->user->getIdEfector();        
    
        $query = Consulta::findActive()
                        ->select('consultas.*')
                        ->leftJoin('turnos', '`turnos`.`id_turnos` = `consultas`.`parent_id` AND `consultas`.`parent_class` = '.Consulta::PARENT_TURNOS)
                        ->leftJoin('turnos', '`turnos`.`id_turnos` = `consultas`.`id_turnos` AND `consultas`.`id_turnos` != 0')
                        ->leftJoin('rr_hh', '`turnos`.`id_rr_hh` = `rr_hh`.`id_rr_hh`')
                        ->leftJoin('personas', '`rr_hh`.`id_persona` = `personas`.`id_persona`')                        
                        ->andWhere(['personas.id_user' => $id_user])
                        ->andWhere('turnos.id_efector = :id_efector',[':id_efector' => $id_efector])
                        ->orderBy('turnos.fecha DESC,turnos.hora DESC');
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id_consulta' => $this->id_consulta,
            'id_turnos' => $this->id_turnos,
            'hora' => $this->hora,
            'id_tipo_consulta' => $this->id_tipo_consulta,
        ]);

        $query->andFilterWhere(['like', 'consulta_inicial', $this->consulta_inicial])
            ->andFilterWhere(['like', 'motivo_consulta', $this->motivo_consulta])
            ->andFilterWhere(['like', 'observacion', $this->observacion])
            ->andFilterWhere(['like', 'control_embarazo', $this->control_embarazo]);

        return $dataProvider;
    }      
    
    //LISTADO DE CONSULTAS DE UNA PERSONA DETERMINADA--------------------------------
    public function searchConsultasPersona($params)
    {        
        $expression = new \yii\db\Expression('NOW()');
        $fecha_hora = (new \yii\db\Query)->select($expression)->scalar();
        list($fecha_hoy,$hora) = explode(" ", $fecha_hora);
        
        $query = Consulta::find()
                        ->select('*')
                        ->leftJoin('turnos', '`turnos`.`id_turnos` = `consultas`.`id_turnos`')
                        ->leftJoin('personas', '`turnos`.`id_persona` = `personas`.`id_persona`')
                        ->where(['personas.id_persona' => $this->id_persona])
                        ->orderBy('turnos.fecha DESC,turnos.hora DESC');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id_consulta' => $this->id_consulta,
            'id_turnos' => $this->id_turnos,
            'hora' => $this->hora,
            'id_tipo_consulta' => $this->id_tipo_consulta,
        ]);

        $query->andFilterWhere(['like', 'consulta_inicial', $this->consulta_inicial])
            ->andFilterWhere(['like', 'motivo_consulta', $this->motivo_consulta])
            ->andFilterWhere(['like', 'observacion', $this->observacion])
            ->andFilterWhere(['like', 'control_embarazo', $this->control_embarazo]);

        return $dataProvider;
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchParaReporteC4($id_efector, $idServicio, $idMedico,  $desde, $hasta, $tipoAtencion)
    {
        if($tipoAtencion == 'AMB'){
            $genericoAMB = '%GenericoAMB';
            $turno = '%Turno';
            $query1 = (new \yii\db\Query())
            ->select(['consultas.id_consulta,
                        personas.id_persona,
                        CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                        personas.documento,
                        personas.fecha_nacimiento,
                        personas.sexo_biologico,
                        turnos.fecha,
                        turnos.hora'])    
                        ->from('consultas')        
                        ->leftJoin('turnos', '`turnos`.`id_turnos` = `consultas`.`parent_id` AND `consultas`.`parent_class` like "'.$turno.'"')                        
                        ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                        ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                        ->andWhere('turnos.fecha >= :desde',[':desde' => $desde])
                        ->andWhere('turnos.fecha <= :hasta',[':hasta' => $hasta])
                        ->andWhere('consultas.id_rr_hh = :idrrhhasigando',[':idrrhhasigando' => $idMedico])
                        ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                        ;
                    $query2 = (new \yii\db\Query())
                        ->select(['consultas.id_consulta,
                                    personas.id_persona,
                                    CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                                    personas.documento,
                                    personas.fecha_nacimiento,
                                    personas.sexo_biologico,
                                    DATE_FORMAT(consultas.created_at, "%Y-%m-%d") as fecha,
                                    DATE_FORMAT(consultas.created_at, "%H:%i:%s") as hora'])    
                                    ->from('consultas')                                
                                    ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                                    ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                                    ->andWhere('DATE_FORMAT(consultas.created_at, "%Y-%m-%d") >= :desde',[':desde' => $desde])
                                    ->andWhere('DATE_FORMAT(consultas.created_at, "%Y-%m-%d") <= :hasta',[':hasta' => $hasta])
                                    ->andWhere('consultas.id_rr_hh = :idrrhhasigando',[':idrrhhasigando' => $idMedico])
                                    ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                                    ->andWhere('`consultas`.`parent_class` like "'.$genericoAMB.'"')
                                    ;        
    
        $resultados = (new \yii\db\Query())
                        ->from(['resultados' => $query1->union(
                            $query2)])
                            ->orderBy(['fecha' => SORT_ASC,'hora' => SORT_ASC])
                            //->createCommand()->getRawSql(); die();
                            ->all();

        }else{
            $genericoEMER = '%GenericoEMER';
            $guardia = '%Guardia';
            $query1 = (new \yii\db\Query())
            ->select(['consultas.id_consulta,
                        personas.id_persona,
                        CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                        personas.documento,
                        personas.fecha_nacimiento,
                        personas.sexo_biologico,
                        guardia.fecha,
                        guardia.hora'])    
                        ->from('consultas')        
                        ->leftJoin('guardia', '`guardia`.`id` = `consultas`.`parent_id` AND `consultas`.`parent_class` like "'.$guardia.'"')                        
                        ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                        ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                        ->andWhere('guardia.fecha >= :desde',[':desde' => $desde])
                        ->andWhere('guardia.fecha <= :hasta',[':hasta' => $hasta])
                        ->andWhere('consultas.id_rr_hh = :idrrhhasigando',[':idrrhhasigando' => $idMedico])
                        ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                        ;
                    $query2 = (new \yii\db\Query())
                        ->select(['consultas.id_consulta,
                                    personas.id_persona,
                                    CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                                    personas.documento,
                                    personas.fecha_nacimiento,
                                    personas.sexo_biologico,
                                    DATE_FORMAT(consultas.created_at, "%Y-%m-%d") as fecha,
                                    DATE_FORMAT(consultas.created_at, "%H:%i:%s") as hora'])    
                                    ->from('consultas')                                
                                    ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                                    ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                                    ->andWhere('DATE_FORMAT(consultas.created_at, "%Y-%m-%d") >= :desde',[':desde' => $desde])
                                    ->andWhere('DATE_FORMAT(consultas.created_at, "%Y-%m-%d") <= :hasta',[':hasta' => $hasta])
                                    ->andWhere('consultas.id_rr_hh = :idrrhhasigando',[':idrrhhasigando' => $idMedico])
                                    ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                                    ->andWhere('`consultas`.`parent_class` like "'.$genericoEMER.'"')
                                    ;        
    
            $resultados = (new \yii\db\Query())
                        ->from(['resultados' => $query1->union(
                            $query2)])
                            ->orderBy(['fecha' => SORT_ASC,'hora' => SORT_ASC])
                            //->createCommand()->getRawSql(); die();
                            ->all();
        }

        return $resultados;
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchParaReporte5($id_efector, $idServicio, $fecha)
    {   
       
        $resultados = (new \yii\db\Query())
        ->select([' DATE(c.created_at) as fecha,
                    DAY(c.created_at) as dia ,
                    sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) < 1 ,1, 0) ) as menor1anioM,
                    sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) < 1 ,1, 0) ) as menor1anioF,
                    sum(IF(p.sexo = "M" and (TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) >= 1 and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) < 2) ,1, 0) ) as 1anioM,
                    sum(IF(p.sexo = "F" and (TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) >= 1 and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) < 2) ,1, 0) ) as 1anioF,
                    sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) BETWEEN 2 and  4 ,1, 0) ) as 2a4M,
                    sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) BETWEEN 2 and  4 ,1, 0) ) as 2a4F,
                    sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) BETWEEN 5 and  9 ,1, 0) ) as 5a9M,
                    sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) BETWEEN 5 and  9 ,1, 0) ) as 5a9F,
                    sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) BETWEEN 10 and  14 ,1, 0) ) as 10a14M,
                    sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) BETWEEN 10 and  14 ,1, 0) ) as 10a14F,
                    sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) BETWEEN 15 and  49 ,1, 0) ) as 15a49M,
                    sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) BETWEEN 15 and  49 ,1, 0) ) as 15a49F,
                    sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) >= 50 ,1, 0) ) as mayor50M,
                    sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,c.created_at) >= 50 ,1, 0) ) as matyor50F
                    '])    
                    ->from('consultas c')   
                    ->leftJoin('personas p', '`c`.`id_persona` = `p`.`id_persona`')
                    ->andWhere('c.id_efector = :id_efector',[':id_efector' => $id_efector])
                    ->andWhere('c.id_servicio = :idServicio',[':idServicio' => $idServicio])
                    ->andWhere('YEAR(c.created_at) = :anio', [':anio' => date('Y', strtotime($fecha))])                             
                    ->andWhere('MONTH(c.created_at) = :mes',[':mes' => date('m', strtotime($fecha))])
                    ->groupBy(['DATE(c.created_at)'])
                    ->orderBy(['c.created_at' => SORT_ASC])
                    /*->createCommand()->getRawSql(); 
                    echo $resultados;
                    die();*/
                    ->all();
                    

            return $resultados;        
    }


    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchReporteFarmacia($id_efector, $idServicio, $fecha, $tipoAtencion)
    {
        if($tipoAtencion == 'AMB'){
            $genericoAMB = '%GenericoAMB';
            $turno = '%Turno';
            $query1 = (new \yii\db\Query())
            ->select(['consultas.id_consulta,
                        personas.id_persona,
                        CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                        personas.documento,
                        personas.fecha_nacimiento,
                        personas.sexo_biologico,
                        turnos.fecha,
                        turnos.hora'])    
                        ->from('consultas')      
                        ->leftJoin('turnos', '`turnos`.`id_turnos` = `consultas`.`parent_id` AND `consultas`.`parent_class` like "'.$turno.'"')                        
                        ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                        ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                        ->andWhere('turnos.fecha = :fecha',[':fecha' => $fecha])                        
                        ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                        ;
                    $query2 = (new \yii\db\Query())
                        ->select(['consultas.id_consulta,
                                    personas.id_persona,
                                    CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                                    personas.documento,
                                    personas.fecha_nacimiento,
                                    personas.sexo_biologico,
                                    DATE_FORMAT(consultas.created_at, "%Y-%m-%d") as fecha,
                                    DATE_FORMAT(consultas.created_at, "%H:%i:%s") as hora'])    
                                    ->from('consultas')                                
                                    ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                                    ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                                    ->andWhere('DATE_FORMAT(consultas.created_at, "%Y-%m-%d") = :fecha',[':fecha' => $fecha])                                    
                                    ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                                    ->andWhere('`consultas`.`parent_class` like "'.$genericoAMB.'"')
                                    ;        
    
        $resultados = (new \yii\db\Query())
                        ->from(['resultados' => $query1->union(
                            $query2)])
                            ->orderBy(['fecha' => SORT_ASC,'hora' => SORT_ASC])
                            //->createCommand()->getRawSql(); die();
                            ->all();

        }elseif($tipoAtencion == 'EMER'){
            $genericoEMER = '%GenericoEMER';
            $guardia = '%Guardia';
            $query1 = (new \yii\db\Query())
            ->select(['consultas.id_consulta,
                        personas.id_persona,
                        CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                        personas.documento,
                        personas.fecha_nacimiento,
                        personas.sexo_biologico,
                        guardia.fecha,
                        guardia.hora'])    
                        ->from('consultas')        
                        ->leftJoin('guardia', '`guardia`.`id` = `consultas`.`parent_id` AND `consultas`.`parent_class` like "'.$guardia.'"')                        
                        ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                        ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                        ->andWhere('guardia.fecha = :fecha',[':fecha' => $fecha])
                        ->andWhere('consultas.id_rr_hh = :idrrhhasigando',[':idrrhhasigando' => $idMedico])
                        ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                        ;
                    $query2 = (new \yii\db\Query())
                        ->select(['consultas.id_consulta,
                                    personas.id_persona,
                                    CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                                    personas.documento,
                                    personas.fecha_nacimiento,
                                    personas.sexo_biologico,
                                    DATE_FORMAT(consultas.created_at, "%Y-%m-%d") as fecha,
                                    DATE_FORMAT(consultas.created_at, "%H:%i:%s") as hora'])    
                                    ->from('consultas')                                
                                    ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                                    ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                                    ->andWhere('DATE_FORMAT(consultas.created_at, "%Y-%m-%d") = :fecha',[':fecha' => $fecha])
                                    ->andWhere('consultas.id_rr_hh = :idrrhhasigando',[':idrrhhasigando' => $idMedico])
                                    ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                                    ->andWhere('`consultas`.`parent_class` like "'.$genericoEMER.'"')
                                    ;        
    
            $resultados = (new \yii\db\Query())
                        ->from(['resultados' => $query1->union(
                            $query2)])
                            ->orderBy(['fecha' => SORT_ASC,'hora' => SORT_ASC])
                            //->createCommand()->getRawSql(); die();
                            ->all();
        }else{            
            $internacion = '%Internacion';
            $resultados = (new \yii\db\Query())
                        ->select(['consultas.id_consulta,
                                    personas.id_persona,
                                    CONCAT(personas.apellido," ",personas.nombre) as nombreyapellido,
                                    personas.documento,
                                    personas.fecha_nacimiento,
                                    personas.sexo_biologico,
                                    DATE_FORMAT(consultas.created_at, "%Y-%m-%d") as fecha,
                                    DATE_FORMAT(consultas.created_at, "%H:%i:%s") as hora'])    
                                    ->from('consultas')                                
                                    ->leftJoin('personas', '`consultas`.`id_persona` = `personas`.`id_persona`')
                                    ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                                    ->andWhere('DATE_FORMAT(consultas.created_at, "%Y-%m-%d") = :fecha',[':fecha' => $fecha])                                    
                                    ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                                    ->andWhere('`consultas`.`parent_class` like "'.$internacion.'"')
                                    ->orderBy(['fecha' => SORT_ASC,'hora' => SORT_ASC])
                                    ->all();

        }
        return $resultados;    
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchReporteOdontologia($id_efector, $idServicio, $fecha)
    {
        $resultados = (new \yii\db\Query())
            ->select('consultas_odontologia_practicas.codigo, count(consultas.id_consulta) as cantidad')    
                ->from('consultas')                                
                ->leftJoin('consultas_odontologia_practicas', '`consultas`.`id_consulta` = `consultas_odontologia_practicas`.`id_consulta`')
                ->andWhere('consultas.id_efector = :id_efector',[':id_efector' => $id_efector])
                ->andWhere('DATE_FORMAT(consultas.created_at, "%Y-%m") = :fecha',[':fecha' => $fecha])                                    
                ->andWhere('consultas.id_servicio = :idServicio',[':idServicio' => $idServicio])
                ->andWhere('consultas_odontologia_practicas.codigo is not NULL')                
                ->orderBy(['consultas.created_at' => SORT_ASC])
                ->groupBy(['consultas_odontologia_practicas.codigo'])
                //->createCommand()->getRawSql(); die();
                ->all();        
        return $resultados;    
    }    
    
}
