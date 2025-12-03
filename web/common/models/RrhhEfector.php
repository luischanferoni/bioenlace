<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "rrhh_efector".
 *
 * @property int $id_rr_hh Codigo de recurso humano
 * @property int $id_persona
 * @property int $id_efector Codigo del efector
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 *
 * @property Efector $efector
 * @property Persona $persona
 */
class RrhhEfector extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'rrhh_efector';
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
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_rr_hh', 'id_persona', 'id_efector', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['id_rr_hh_viejo', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [
                ['id_persona', 'id_efector', 'deleted_at'],
                'unique',
                'message' => 'Al parecer el usuario ya se encuentra asignado al/los efectores seleccionados',
                'targetAttribute' => ['id_persona', 'id_efector', 'deleted_at'],
                'when' => function ($model) {
                    $model->clearErrors();
                },
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_rr_hh' => 'Id RRHH',
            'id_persona' => 'Persona',
            'id_efector' => 'Efector',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfector()
    {
        $efector = Efector::find()->andWhere(['id_efector' => $this->id_efector])->one();

        if (!$efector){
            $efectorVacio = new Efector();
            $efectorVacio->nombre = 'SIN EFECTOR';
            return $efectorVacio;
        }

        return $efector;
        //return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhServicio()
    {
        return $this->hasMany(RrhhServicio::className(), ['id_rr_hh' => 'id_rr_hh'])
            ->onCondition('rrhh_servicio.deleted_at is NULL');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhServiciosEliminados()
    {
        return $this->hasMany(RrhhServicio::className(), ['id_rr_hh' => 'id_rr_hh'])
                    ->onCondition('rrhh_servicio.deleted_at is NOT NULL');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhServicioConAgenda()
    {
        return $this->hasMany(RrhhServicio::className(), ['id_rr_hh' => 'id_rr_hh'])
            ->leftJoin('servicios', ['servicios.id_servicio' => 'rrhh_servicio.id_servicio', 'servicios.acepta_turnos' => 'SI']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhLaboral()
    {
        return $this->hasMany(RrhhLaboral::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    /**
     * getEfectores devuelve todos los efectores en los que la persona brinda servicios como rrhh
     *
     * @return \yii\db\ActiveQuery
     */
    public static function getEfectores($id_persona)
    {
        $rrhh_efectores = RrhhEfector::find()
            ->select(['rrhh_efector.id_rr_hh', 'rrhh_efector.id_efector', 'efectores.nombre AS nombre', 'efectores.id_localidad AS id_localidad'])
            ->andWhere(['id_persona' => $id_persona])
            ->andWhere('rrhh_efector.deleted_at IS NULL')
            ->innerJoin('efectores', 'efectores.id_efector = rrhh_efector.id_efector')
            ->innerJoin(
                'rrhh_servicio',
                'rrhh_servicio.id_rr_hh = rrhh_efector.id_rr_hh AND rrhh_servicio.deleted_at IS NULL'
            )
            ->asArray()
            ->all();
        return $rrhh_efectores;
    }

    /**
     * Devuelve un listdo de recursos humanos para un efector, 
     * ordenado por disponibilidad de turnos
     * @param type $id_efector
     * @return \yii\db\ActiveQuery
     */
    public function ordenadosPorTurno($id_efector, $id_persona)
    {
        $and = '';
        if ($id_persona) {
            $and = 'AND rr_hh.id_persona = ' . $id_persona;
        }

        return RrhhServicio::find()
            ->select(['rrhh_servicio.id', 'rrhh_efector.id_rr_hh', 'rrhh_servicio.id_servicio'])
            ->leftJoin(
                'rrhh_efector',
                'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh'
            )
            ->leftJoin('servicios', 'rrhh_servicio.id_servicio = servicios.id_servicio')
            ->where(['servicios.acepta_turnos' => 'SI'])
            ->andWhere(['rrhh_efector.id_efector' => Yii::$app->user->getIdEfector()])
            ->andWhere(['rrhh_efector.deleted_at' => null])
            ->orderBy('rrhh_servicio.id_servicio')
            ->all();
    }
    
    /**
     * Devuelve un array con informacion del servicio actual del rrhh, de acuerdo a su agenda
     */
    public static function obtenerServicioActual()
    {
        // numero de dia actual y hora actual
        $nroDiaDeSemana = date('N') - 1;
        $horaActual = date("H");
        // todos los servicios del rrhh
        $servicios = Yii::$app->user->getServicioYhorarioDeTurno();

        if (!isset($servicios[$nroDiaDeSemana])) {
            return ['enTurno' => false, 'id_servicio' => 0, 'servicio' => 'ACTUALMENTE EN NINGUN SERVICIO', 'hasta' => ''];
        }

        foreach ($servicios[$nroDiaDeSemana] as $servicio => $value) {
            if ($horaActual >= $value['horaInicial'] && $horaActual <= $value['horaFinal']) {
                return [
                    'enTurno' => true, 
                    'id_servicio' => $servicio,  
                    'servicio' => $value['nombreServicio'], 
                    'hasta' => date("g:i a", strtotime($value['horaFinal'] . ":00"))
                ];
            }
        }

        return ['enTurno' => false, 'id_servicio' => -1, 'servicio' => 'ACTUALMENTE EN NINGUN SERVICIO', 'hasta' => ''];
    }

    //Metodo para obtener todos los servicios que presta el RRHH.

    public static function obtenerServicios(){

        $agenda = Yii::$app->user->getServicioYhorarioDeTurno();

        $servicios=[];

        foreach($agenda as $key => $dia){

            foreach ($dia as $servicio => $value) {
                
                if (!in_array($value['nombreServicio'],$servicios))
                $servicios[]= $value['nombreServicio'];
            }
    }
        return $servicios;
    }

    /*
     * Busca rrhh en un efector por nombre y/o apellido
     */
    public static function personasLiveSearch($q, $idEfector)
    {
        $out = ['id' => '', 'text' => ''];

        $query = new yii\db\Query;
        
        $query->select(["CONCAT(CONCAT(apellido, ', ', nombre), ' - ', documento) AS text",
                         '`personas`.id_persona AS id'])
            ->from('personas')
            ->where(['like', 'CONCAT(apellido," ",nombre)', '%'.$q.'%', false])
            ->orwhere(['like', 'nombre', '%'.$q.'%', false])
            ->orwhere(['like', 'apellido', $q.'%', false])
            ->orWhere(['like', 'documento', $q.'%', false])
            ->rightJoin(
                'rrhh_efector',
                'rrhh_efector.id_persona = personas.id_persona AND rrhh_efector.id_efector = '.$idEfector
            )            
            ->limit(20);

        $command = $query->createCommand();
        
        $data = $command->queryAll();

        $out = array_values($data);

        return $out;
    }
    
    public static function obtenerMedicosPorEfector($id_efector){

       return RrhhEfector::find()
        ->select(['rrhh_efector.id_rr_hh','rrhh_servicio.id', 'personas.id_persona', 'CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " " ,COALESCE(personas.otro_nombre,""), " - ", servicios.nombre) AS datos'])
        ->where(['rrhh_efector.id_efector' => $id_efector])
        ->join('LEFT JOIN', 'rrhh_servicio', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
        ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rrhh_servicio.id_servicio')
        ->andWhere('servicios.nombre IN ("MED CLINICA", "ODONTOLOGIA","PEDIATRIA","GINECOLOGIA","OBSTETRICIA","MED FAMILIAR","MED GENERAL","NEUROLOGIA","CARDIOLOGIA","INMUNOLOGIA CLINICA Y ALERGOLOGIA","GASTROENTEROLOGIA","OFTALMOLOGIA","ENDOCRINOLOGIA","TRAUMATOLOGIA","NEUMUNOLOGIA","CIRUGIA GENERAL","DIABETES","GERIATRIA","TERAPIA INTENSIVA","PSIQUIATRÍA","NEFROLOGÍA","UROLOGÍA","HEMATOLOGÍA","OTORRINOLARINGOLOGÍA")')
        ->join('LEFT JOIN', 'personas', 'rrhh_efector.id_persona = personas.id_persona')
        ->asArray()
        ->all();

    }

    public static function obtenerProfesionalesParches($q){

        $out = ['id' => '', 'text' => ''];

        $data = RrhhEfector::find()
         ->select(['rrhh_efector.id_rr_hh AS id', 'CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " " ,COALESCE(personas.otro_nombre,""), " - ", efectores.nombre) AS text'])         
         ->join('JOIN', 'efectores', 'rrhh_efector.id_efector = efectores.id_efector')
         ->join('LEFT JOIN', 'rrhh_servicio', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
         ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rrhh_servicio.id_servicio')
         ->where('servicios.nombre IN ("MED CLINICA", "PEDIATRIA","GINECOLOGIA","OBSTETRICIA","MED FAMILIAR","MED GENERAL","ENDOCRINOLOGIA", "GERIATRIA","APS", "ENFERMERIA")')
         ->join('LEFT JOIN', 'personas', 'rrhh_efector.id_persona = personas.id_persona')         
         ->andWhere(['like', 'CONCAT(personas.apellido, " ", personas.nombre)', '%'.$q.'%', false])
         ->orWhere(['like', 'CONCAT(personas.nombre, " ", personas.apellido)', '%'.$q.'%', false])
         ->orWhere(['like', 'CONCAT(personas.nombre, " ", COALESCE(personas.otro_nombre,""))', '%'.$q.'%', false])
         ->orWhere(['like', 'CONCAT(personas.nombre, " ", COALESCE(personas.otro_nombre,""), " ", personas.apellido)', '%'.$q.'%', false])
         ->orWhere(['like', 'personas.nombre', '%'.$q.'%', false])
         ->orWhere(['like', 'personas.otro_nombre', '%'.$q.'%', false])
         ->orwhere(['like', 'personas.apellido', '%'.$q.'%', false])    
         ->asArray()
         ->all();

         $out = array_values($data);

        return $out;
 
     }

     public static function obtenerMedicosPorServicioEfector($id_efector, $id_servicio){

        return RrhhEfector::find()
         ->select(['rrhh_efector.id_rr_hh','rrhh_servicio.id', 'personas.id_persona', 'CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " " ,COALESCE(personas.otro_nombre,""), " - ", servicios.nombre) AS datos'])
         ->where(['rrhh_efector.id_efector' => $id_efector])
         ->join('LEFT JOIN', 'rrhh_servicio', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
         ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rrhh_servicio.id_servicio')
         ->andWhere(['servicios.id_servicio' => $id_servicio])
         ->join('LEFT JOIN', 'personas', 'rrhh_efector.id_persona = personas.id_persona')
         ->asArray()
         ->all();
 
     }

}
