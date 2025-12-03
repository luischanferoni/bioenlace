<?php

namespace common\models;

use Yii;


/**
 * This is the model class for table "ServiciosEfector".
 *
 * @property string $id_servicio
 * @property integer $id_efector
 */
class ServiciosEfector extends \yii\db\ActiveRecord
{
    public $rrhhs;
    use \common\traits\SoftDeleteDateTimeTrait;
    
    const ORDEN_LLEGADA_PARA_TODOS = 'ORDEN_LLEGADA_PARA_TODOS';
    const DELEGAR_A_CADA_RRHH = 'DELEGAR_A_CADA_RRHH';
    const DERIVACION_DELEGAR_A_CADA_RRHH = 'DERIVACION_DELEGAR_A_CADA_RRHH';
    const DERIVACION_ORDEN_LLEGADA_PARA_TODOS = 'DERIVACION_ORDEN_LLEGADA_PARA_TODOS';

    const FORMAS_ATENCION = [
            self::DELEGAR_A_CADA_RRHH => 'El paciente puede elegir el médico', 
            self::ORDEN_LLEGADA_PARA_TODOS => 'Se atiende a todos por orden de llegada',
            self::DERIVACION_DELEGAR_A_CADA_RRHH => 'Solo con DERIVACIÓN, puede elegir el médico', 
            self::DERIVACION_ORDEN_LLEGADA_PARA_TODOS => 'Solo con DERIVACIÓN, por orden de llegada'
        ];    


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'servicios_efector';
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
            [['id_servicio', 'id_efector', 'formas_atencion'], 'required'],
            [['id_servicio', 'id_efector', 'pase_previo'], 'integer'],
            [
                ['id_servicio', 'id_efector'], 'unique',
                'message' => 'Este servicio ya existe en este efector (compruebe los servicios eliminados)',
                'targetAttribute' => ['id_servicio', 'id_efector']
               
            ],
            [['pase_previo'], 'default', 'value'=> 0]
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
            'horario' => 'Horario del Servicio',
            'nombreServicio' => 'Servicio',
            'nombreEfector' => 'Efector',
            'pase_previo' => 'Antes del turno, pasar previamente por',

        ];
    }
    public function getNombreServicio()
    {
        return $this->servicio->nombre;
    }

    public function getNombreEfector()
    {
        return $this->efector->nombre;
    }

    public function getServicio()
    {
        return $this->hasOne(Servicio::className(), ['id_servicio' => 'id_servicio']);
    }

    public function getServicioAceptaAgenda()
    {
        return $this->hasOne(Servicio::className(), ['id_servicio' => 'id_servicio'])
                    ->andOnCondition(['servicios.acepta_turnos' => 'SI']);
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }

    public function getPasePrevio()
    {
        return $this->hasOne(Servicio::className(), ['id_servicio' => 'pase_previo']);
    }

    public static function serviciosPorEfector($idEfector)
    {
        return self::find()->where('id_efector = '. $idEfector)->all();
    }

    public static function serviciosPorEfectorConDerivacion($idEfector)
    {
        return self::find()->where('id_efector = '. $idEfector)->all();
    }

    public static function serviciosPorEfectorSinDerivacion($idEfector)
    {
        return self::find()->where('id_efector = '. $idEfector)->andWhere->all();
    }

    public static function serviciosXEfector($id_efector)
    {
        $query = self::find()->select('servicios_efector.id_servicio, servicios.nombre')
        ->where(['servicios_efector.id_efector' => $id_efector])
        ->join('INNER JOIN', 'servicios', 'servicios.id_servicio = servicios_efector.id_servicio')
        ->andWhere(['servicios.acepta_turnos' => 'SI'])
        ->asArray()
        ->all();


        return $query;
    }


    /**
     * Busca todos los servicios del efector, que atiendan al publico servicioAceptaAgenda en true
     */
    public static function rrhhPorServiciosAgendaPorEfector($id_efector, $id_servicio_practica = null)
    {
        $query = self::findActive()
                    ->andWhere(['servicios_efector.id_efector' => $id_efector])
                    ->joinWith('servicioAceptaAgenda', true, $joinType = 'INNER JOIN');

        if($id_servicio_practica !== null):
            $query->andWhere(['servicios_efector.id_servicio' => $id_servicio_practica]);
        endif;

        // Todos los servicios del Efector
        $serviciosEfector = $query->all();

        $idsServicios = [];        
        $arrayServiciosEfector['CON_DERIVACION'] = [];
        $arrayServiciosEfector['SIN_DERIVACION'] = [];

        foreach ($serviciosEfector as $servicioEfector) {
            $idsServicios[] = $servicioEfector->id_servicio;
            switch ($servicioEfector->formas_atencion) {
                case self::DELEGAR_A_CADA_RRHH:
                    $arrayServiciosEfector['SIN_DERIVACION'][] = $servicioEfector;                    
                    break;
                case self::ORDEN_LLEGADA_PARA_TODOS:
                    $arrayServiciosEfector['SIN_DERIVACION'][] = $servicioEfector;                    
                    break;
                case self::DERIVACION_DELEGAR_A_CADA_RRHH:                    
                    $arrayServiciosEfector['CON_DERIVACION'][] = $servicioEfector;
                    break;
                case self::DERIVACION_ORDEN_LLEGADA_PARA_TODOS:
                    $arrayServiciosEfector['CON_DERIVACION'][] = $servicioEfector;
                    break;
                default:
                    # code...
                    break;
            }
        }

        // Todos los RRHH para cada servicio
        $rrhhsServicio = RrhhServicio::findActive()
                ->joinWith('rrhhEfector')
                ->andWhere(['rrhh_efector.id_efector' => $id_efector])
                //->andWhere(['in', 'id_servicio', $idsServicios])
                ->orderBy('id_servicio')
                ->all();

        foreach ($rrhhsServicio as $rrhhServicio) {
            $arrayRrhhServicios[$rrhhServicio->id_servicio][] = $rrhhServicio;
        }
  //      var_dump($arrayRrhhServicios[4]);die;

        foreach ($arrayServiciosEfector['CON_DERIVACION'] as $key => $servicioEfector) {
            if (!isset($arrayRrhhServicios[$servicioEfector->id_servicio])) {
                unset($arrayServiciosEfector['CON_DERIVACION'][$key]);
                continue;
            }
            $servicioEfector->rrhhs = [];
            if ($servicioEfector->formas_atencion == self::DERIVACION_DELEGAR_A_CADA_RRHH) {
                $servicioEfector->rrhhs = isset($arrayRrhhServicios[$servicioEfector->id_servicio])?$arrayRrhhServicios[$servicioEfector->id_servicio]:[];
            }
        }
        
        foreach ($arrayServiciosEfector['SIN_DERIVACION'] as $key => $servicioEfector) {
            if (!isset($arrayRrhhServicios[$servicioEfector->id_servicio])) {
                unset($arrayServiciosEfector['SIN_DERIVACION'][$key]);
                continue;
            }            
            $servicioEfector->rrhhs = [];
            if ($servicioEfector->formas_atencion == self::DELEGAR_A_CADA_RRHH) {
                $servicioEfector->rrhhs = isset($arrayRrhhServicios[$servicioEfector->id_servicio])?$arrayRrhhServicios[$servicioEfector->id_servicio]:[];
            }
        }
      
        return $arrayServiciosEfector;

    }

    public static function allServiciosXEfector($id_efector)
    {
        $query = self::find()->select('servicios_efector.id_servicio, servicios.nombre')
        ->where(['servicios_efector.id_efector' => $id_efector])
        ->join('INNER JOIN', 'servicios', 'servicios.id_servicio = servicios_efector.id_servicio')        
        ->asArray()
        ->all();


        return $query;
    }
}
