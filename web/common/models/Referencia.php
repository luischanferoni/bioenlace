<?php

namespace common\models;

use Yii;
use common\models\Clinical\Encounter;
use common\traits\LegacyIdConsultaAsEncounterColumnTrait;

/**
 * This is the model class for table "referencia".
 *
 * @property string $id_referencia
 * @property int|null $legacy_id_consulta Encounter id (columna legacy `id_consulta` pre-migración).
 * @property int|null $encounter_id Alias de {@see $legacy_id_consulta}.
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
 * @property-read Encounter|null $encounter
 * @property-read Efector|null $efectorReferenciado
 * @property-read MotivoDerivacion|null $motivoDerivacion
 * @property-read Servicio|null $servicio
 * @property-read Estado_solicitud|null $estadoSolicitud
 */
class Referencia extends \yii\db\ActiveRecord
{
    use LegacyIdConsultaAsEncounterColumnTrait;

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
        $fk = static::legacyConsultaFkAttribute();

        return [
            [[$fk, 'id_efector_referenciado', 'id_motivo_derivacion', 'id_servicio', 'id_estado'], 'required'],
            [[$fk, 'id_efector_referenciado', 'id_motivo_derivacion', 'id_servicio', 'id_estado'], 'integer'],
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
            static::legacyConsultaFkAttribute() => 'Encounter',
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

    /** @deprecated use {@see getEncounter()} */
    public function getConsulta()
    {
        return $this->getEncounter();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfectorReferenciado()
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'id_efector_referenciado']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMotivoDerivacion()
    {
        return $this->hasOne(MotivoDerivacion::class, ['id_motivo_derivacion' => 'id_motivo_derivacion']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getServicio()
    {
        return $this->hasOne(Servicio::class, ['id_servicio' => 'id_servicio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEstadoSolicitud()
    {
        return $this->hasOne(Estado_solicitud::class, ['id_estado' => 'id_estado']);
    }
    
    public function getDatosPersona($idEncounter, $idreferencia)
    {
        $fk = static::legacyConsultaFkAttribute();
        $persona = Referencia::find()->asArray()->select(
                        ['id_referencia' => 'referencia.id_referencia',
                         'id_consulta' => 'enc.id',
                         'id_turno' => 'turnos.id_turnos',
                         'id_persona' => 'personas.id_persona',
                         'nombre' => 'personas.nombre',  
                         'apellido' => 'personas.apellido',
                         'id_efector' => 'turnos.id_efector'
                            ])
                        ->from('referencia')
                        ->innerJoin(['enc' => Encounter::tableName()], "referencia.{$fk} = enc.id")
                        ->leftJoin('turnos', 'enc.appointment_id = turnos.id_turnos')
                        ->innerJoin('personas', 'enc.subject_persona_id = personas.id_persona')
                        ->where(["referencia.{$fk}" => (int) $idEncounter])
                        ->andwhere(['id_referencia' => $idreferencia])
                        ->orderBy('apellido')->all();
        return $persona;
    }
    
    public function getDatosPersonaxIdConsulta($idEncounter)
    {
        $fk = static::legacyConsultaFkAttribute();
        $persona = Referencia::find()->asArray()->select(
                        ['id_consulta' => 'enc.id',
                         'id_turno' => 'turnos.id_turnos',
                         'id_persona' => 'personas.id_persona',
                         'nombre' => 'personas.nombre',  
                         'apellido' => 'personas.apellido',
                         'fecha' => 'turnos.fecha',
                         'hora' => 'turnos.hora',
                            ])
                        ->from(['enc' => Encounter::tableName()])
                        ->leftJoin('turnos', 'enc.appointment_id = turnos.id_turnos')
                        ->innerJoin('personas', 'enc.subject_persona_id = personas.id_persona')
                        ->where(['enc.id' => (int) $idEncounter])
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
                    
                    $persona = Referencia::getDatosPersona($model->getEncounter_id(), $this->id_referencia);
                    
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
                    $mensajes->texto =  "Referencia generada para el encounter: ".$model->getEncounter_id()."<br>"
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
        
        
    public function getUsuarioPorIdEfectorIdServicio($idefector, $idservicio)
    {
        $idefector = (int) $idefector;
        $idservicio = (int) $idservicio;

        $qPes = (new \yii\db\Query())
            ->select([
                'id_servicio' => 'pes.id_servicio',
                'id_profesional_efector_servicio' => 'pes.id',
                'id_persona' => 'pes.id_persona',
                'id_user' => 'personas.id_user',
                'username' => 'user.username',
            ])
            ->from(['pes' => 'profesional_efector_servicio'])
            ->innerJoin('personas', 'pes.id_persona = personas.id_persona')
            ->innerJoin('user', 'personas.id_user = user.id')
            ->where([
                'pes.id_efector' => $idefector,
                'pes.id_servicio' => $idservicio,
            ])
            ->andWhere(['pes.deleted_at' => null]);

        return $qPes->orderBy(['username' => SORT_ASC])->all();
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
        return (new \yii\db\Query())
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
