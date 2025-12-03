<?php

namespace common\models;

use Yii;
use common\models\Consulta;
/**
 * This is the model class for table "referencia".
 *
 * @property string $id_referencia
 * @property string $id_consulta
 * @property integer $id_efector_referenciado
 * @property string $id_motivo_derivacion
 * @property string $id_servicio
 * @property string $estudios_complementarios
 * @property string $resumen_hc
 * @property string $tratamiento_previo
 * @property string $tratamiento
 * @property string $id_estado
 * @property string $fecha_turno
 * @property string $hora_turno
 * @property string $observacion
 *
 * @property Consultas $idConsulta
 * @property Efectores $idEfectorReferenciado
 * @property MotivosDerivacion $idMotivoDerivacion
 * @property Servicios $idServicio
 * @property EstadoSolicitud $idEstado
 */
class Referencia extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'referencia';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_consulta', 'id_efector_referenciado', 'id_motivo_derivacion', 'id_servicio', 'id_estado'], 'required'],
            [['id_consulta', 'id_efector_referenciado', 'id_motivo_derivacion', 'id_servicio', 'id_estado'], 'integer'],
            [['estudios_complementarios', 'resumen_hc', 'tratamiento_previo', 'tratamiento', 'observacion'], 'string'],
            [['fecha_turno', 'hora_turno'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_referencia' => 'Id Referencia',
            'id_consulta' => 'Id Consulta',
            'id_efector_referenciado' => 'Id Efector Referenciado',
            'id_motivo_derivacion' => 'Id Motivo Derivacion',
            'id_servicio' => 'Id Servicio',
            'estudios_complementarios' => 'Estudios Complementarios',
            'resumen_hc' => 'Resumen Hc',
            'tratamiento_previo' => 'Tratamiento Previo',
            'tratamiento' => 'Tratamiento',
            'id_estado' => 'Id Estado',
            'fecha_turno' => 'Fecha Turno',
            'hora_turno' => 'Hora Turno',
            'observacion' => 'Observacion',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
//    public function getIdConsulta()
//    {
//        return $this->hasOne(Consultas::className(), ['id_consulta' => 'id_consulta']);
//    }

    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdEfectorReferenciado()
    {
        return $this->hasOne(Efectores::className(), ['id_efector' => 'id_efector_referenciado']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdMotivoDerivacion()
    {
        return $this->hasOne(MotivosDerivacion::className(), ['id_motivo_derivacion' => 'id_motivo_derivacion']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdServicio()
    {
        return $this->hasOne(Servicios::className(), ['id_servicio' => 'id_servicio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdEstado()
    {
        return $this->hasOne(EstadoSolicitud::className(), ['id_estado' => 'id_estado']);
    }
    
    public function getDatosPersona($idconsulta,$idreferencia)
    {
        $persona = Referencia::find()->asArray()->select(
                        ['id_referencia' => 'referencia.id_referencia',
                         'id_consulta' => 'consultas.id_consulta',
                         'id_turno' => 'consultas.id_turnos',
                         'id_persona' => 'personas.id_persona',
                         'nombre' => 'personas.nombre',  
                         'apellido' => 'personas.apellido',
                         'id_efector' => 'turnos.id_efector'
                            ])
                        ->from('referencia')
                        ->join('INNER JOIN','consultas','referencia.id_consulta=consultas.id_consulta')
                        ->join('INNER JOIN','turnos','consultas.id_turnos=turnos.id_turnos')
                        ->join('INNER JOIN','personas','turnos.id_persona=personas.id_persona')
                        ->where(['referencia.id_consulta' => $idconsulta])
                        ->andwhere(['id_referencia' => $idreferencia])
                        ->orderBy('apellido')->all();
        return $persona;
    }
    
    public function getDatosPersonaxIdConsulta($idconsulta)
    {
        $persona = Referencia::find()->asArray()->select(
                        ['id_consulta' => 'consultas.id_consulta',
                         'id_turno' => 'consultas.id_turnos',
                         'id_persona' => 'personas.id_persona',
                         'nombre' => 'personas.nombre',  
                         'apellido' => 'personas.apellido',
                         'fecha' => 'turnos.fecha',
                         'hora' => 'turnos.hora',
                            ])
                        ->from('consultas')
                        ->join('INNER JOIN','turnos','consultas.id_turnos=turnos.id_turnos')
                        ->join('INNER JOIN','personas','turnos.id_persona=personas.id_persona')
                        ->where(['consultas.id_consulta' => $idconsulta])
                        ->orderBy('apellido')->all();
        return $persona;
    }
        
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $mensajes = new \common\models\Mensajes();
        $model = new Referencia();
        $model->load(Yii::$app->request->post());
        
            if ($insert){
                if ($model->isNewRecord) {
                    
                    $persona = Referencia::getDatosPersona($model->id_consulta,$this->id_referencia);
                    
                    $apeynom=$persona[0]['apellido'].', '.$persona[0]['nombre'];
                    $efectororigen = \common\models\Efector::findOne(['id_efector' => $persona[0]['id_efector']]);
                    $origen = $efectororigen->nombre;
                    $efectordestino = \common\models\Efector::findOne(['id_efector' => $model->id_efector_referenciado]);
                    $destino = $efectordestino->nombre;
                    
                    $derivacion = \common\models\MotivoDerivacion::findOne($model->id_motivo_derivacion);
                    $motivo_derivacion = $derivacion->nombre;
                    
                    if($model->id_motivo_derivacion == 3){
                        // id_motivo_derivacion=3 corresponde a un estudio complementario
                        $estudios_complementarios = $model->estudios_complementarios;
                }else{
                    $estudios_complementarios = "-";
                }
                    if($model->tratamiento_previo == 'SI'){
                        $tratamiento = $model->tratamiento;
                    }else{
                        $tratamiento="-";
                    }
                    $serv = \common\models\Servicio::findOne($model->id_servicio);
                    $servicio = $serv->nombre;
                    
                    
                    $mensajes->id_emisor =  Yii::$app->user->id;
                    $mensajes->id_receptor =  $model->id_efector_referenciado;
                    $mensajes->asunto =  "Referencia";
                    $mensajes->texto =  "Referencia generada para la consulta: ".$model->id_consulta."<br>"
                            . "<p>Establecimiento que deriva: ".$origen."</p>"
                            . "<p>Establecimiento destino: ".$destino."</p>"
                            . "<p>Paciente: ".$apeynom."</p>"
                            . "<p>Motivo de Derivacion: ".$motivo_derivacion."</p>"
                            . "<p>Estudio Complementario: ".$estudios_complementarios."</p>"
                            . "<p>Tratamiento Previo: ".$model->tratamiento_previo."</p>"
                            . "<p>Tratamiento: ".$tratamiento."</p>"
                            . "<p>Servicio: ".$servicio."</p>";
                    $mensajes->fecha = date("Y-m-d");
                    $mensajes->estado = "No leído";
                    $mensajes->save();
                }
            } 
            return true;
        }
        
        
    public function getUsuarioPorIdEfectorIdServicio($idefector,$idservicio)
    {
        $usuarios = common\models\Rrhh_efector::find()->asArray()->select(
                    ['id_servicio' => 'rr_hh_efector.id_servicio',
                        'id_rr_hh' => 'rr_hh_efector.id_rr_hh',
                        'id_persona' => 'rr_hh.id_persona',
                        'id_user' => 'personas.id_user',
                        'username' => 'user.username'
                    ])
                    ->from('rr_hh_efector')
                    ->join('INNER JOIN','rr_hh','rr_hh_efector.id_rr_hh = rr_hh.id_rr_hh')
                    ->join('INNER JOIN','personas','rr_hh.id_persona = personas.id_persona')
                    ->join('INNER JOIN','user','personas.id_user = user.id')
                    ->where(['rr_hh_efector.id_efector' => $idefector])
                    ->andwhere(['rr_hh_efector.id_servicio' => $idservicio])
                    ->orderBy('username')->all();
    }
        
        
        /**
     * Devuelve un listdo de referencias por efector, agrupados por servicios
     * @param type $id_efector
     * @return \yii\db\ActiveQuery
     */
    public function porEfector($id_efector)
    {
        return Referencia::find()
                ->select('*')
                ->where('id_efector_referenciado = '.$id_efector)
                ->andWhere('id_estado = 1')                
                ->orderBy('id_servicio')
                ->all();
    }  
    
    public function cantidadPorEfector($id_efector)
    {
        return (new yii\db\Query())
            ->from('referencia')
            ->where('id_efector_referenciado = '.$id_efector)
            ->andWhere('id_estado = 1')
            ->count();
        // return Referencia::find()
        //         ->select(['COUNT(*) AS cantidad'])
        //         ->where('id_efector_referenciado = '.$id_efector)
        //         ->andWhere('id_estado = 1')
        //         ->all();
    }    
    
}
