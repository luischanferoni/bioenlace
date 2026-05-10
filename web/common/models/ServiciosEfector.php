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
    /** @var ProfesionalEfectorServicio[]|array<int, ProfesionalEfectorServicio> profesionales con PES en el servicio (rellenado en listados de agenda). */
    public $profesionalesPes;
    use \common\traits\SoftDeleteDateTimeTrait;
    
    const ORDEN_LLEGADA_PARA_TODOS = 'ORDEN_LLEGADA_PARA_TODOS';
    const DELEGAR_A_CADA_PROFESIONAL = 'DELEGAR_A_CADA_PROFESIONAL';
    const DERIVACION_DELEGAR_A_CADA_PROFESIONAL = 'DERIVACION_DELEGAR_A_CADA_PROFESIONAL';
    const DERIVACION_ORDEN_LLEGADA_PARA_TODOS = 'DERIVACION_ORDEN_LLEGADA_PARA_TODOS';

    const FORMAS_ATENCION = [
            self::DELEGAR_A_CADA_PROFESIONAL => 'El paciente puede elegir el médico',
            self::ORDEN_LLEGADA_PARA_TODOS => 'Se atiende a todos por orden de llegada',
            self::DERIVACION_DELEGAR_A_CADA_PROFESIONAL => 'Solo con DERIVACIÓN, puede elegir el médico',
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
     * Busca todos los servicios del efector que atiendan al público (`servicioAceptaAgenda`) y arma grupos con profesionales PES.
     */
    public static function profesionalPorServiciosAgendaPorEfector($id_efector, $id_servicio_practica = null)
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
                case self::DELEGAR_A_CADA_PROFESIONAL:
                    $arrayServiciosEfector['SIN_DERIVACION'][] = $servicioEfector;                    
                    break;
                case self::ORDEN_LLEGADA_PARA_TODOS:
                    $arrayServiciosEfector['SIN_DERIVACION'][] = $servicioEfector;                    
                    break;
                case self::DERIVACION_DELEGAR_A_CADA_PROFESIONAL:                    
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

        // Profesionales por servicio (PES en el efector)
        $pesPorServicio = [];
        $filasPes = ProfesionalEfectorServicio::find()
                ->where(['id_efector' => $id_efector, 'deleted_at' => null])
                ->with(['persona', 'agenda'])
                ->orderBy('id_servicio')
                ->all();

        foreach ($filasPes as $pes) {
            $pesPorServicio[$pes->id_servicio][] = $pes;
        }

        foreach ($arrayServiciosEfector['CON_DERIVACION'] as $key => $servicioEfector) {
            if (!isset($pesPorServicio[$servicioEfector->id_servicio])) {
                unset($arrayServiciosEfector['CON_DERIVACION'][$key]);
                continue;
            }
            $servicioEfector->profesionalesPes = [];
            if ($servicioEfector->formas_atencion == self::DERIVACION_DELEGAR_A_CADA_PROFESIONAL) {
                $servicioEfector->profesionalesPes = isset($pesPorServicio[$servicioEfector->id_servicio])?$pesPorServicio[$servicioEfector->id_servicio]:[];
            }
        }
        
        foreach ($arrayServiciosEfector['SIN_DERIVACION'] as $key => $servicioEfector) {
            if (!isset($pesPorServicio[$servicioEfector->id_servicio])) {
                unset($arrayServiciosEfector['SIN_DERIVACION'][$key]);
                continue;
            }            
            $servicioEfector->profesionalesPes = [];
            if ($servicioEfector->formas_atencion == self::DELEGAR_A_CADA_PROFESIONAL) {
                $servicioEfector->profesionalesPes = isset($pesPorServicio[$servicioEfector->id_servicio])?$pesPorServicio[$servicioEfector->id_servicio]:[];
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
