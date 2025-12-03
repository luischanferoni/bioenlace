<?php

namespace common\models;

use common\models\Profesiones;
use common\models\Especialidades;
use common\models\Persona;
use Yii;

/**
 * This is the model class for table "rr_hh".
 *
 * @property string $id_rr_hh
 * @property integer $id_persona
 * @property string $id_profesion
 * @property integer $id_especialidad
 *
 * @property AgendaRrhh[] $agendaRrhhs
 * @property Especialidades $idEspecialidad
 * @property Personas $idPersona
 * @property Profesiones $idProfesion
 * @property Turnos[] $turnos
 */
class Rrhh extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'rr_hh';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_persona', 'id_profesion'], 'required'],
            [['id_persona', 'id_profesion', 'id_especialidad'], 'integer']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_rr_hh' => 'Id Rr Hh',
            'id_persona' => 'Id Persona',
            'id_profesion' => 'Id Profesion',
            'id_especialidad' => 'Id Especialidad',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAgendaRrhhs()
    {
        return $this->hasMany(AgendaRrhh::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdEspecialidad()
    {
        return $this->hasOne(Especialidades::className(), ['id_especialidad' => 'id_especialidad']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdProfesion()
    {
        return $this->hasOne(Profesiones::className(), ['id_profesion' => 'id_profesion']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTurnos()
    {
        return $this->hasMany(Turnos::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    public function getListaProfesiones()
    {        
       $profesiones = Profesiones::find()->indexBy('id_profesion')->asArray()->all(); 
       return \yii\helpers\ArrayHelper::map($profesiones, 'id_profesion', 'nombre');
    }
    
    public static function getListaEspecialidadesXprofesion($idprofesion)
    {
        $especialidades = Especialidades::find()->asArray()->select(['id' => 'id_especialidad', 'name' => 'nombre'])
                        ->from('especialidades')
                        ->where(['id_profesion' => $idprofesion])
                        ->orderBy('nombre')->all();
        return $especialidades;
    }
    
    public function getListaCondicioneslaborales()
    {
        $condiciones = Rrhh::find()->asArray()->select('id_condicion_laboral', 'nombre')
                        ->from('condiciones_laborales')
                        ->orderBy('nombre')->all();
        return $condiciones;
    }
    
    public function getListaServiciosXefector($idefector)
    {
        $servicios = Servicio::find()->asArray()->select(['id' => 'servicios.id_servicio', 'name' => 'nombre'])
                        ->from('servicios')
                        ->join('INNER JOIN','ServiciosEfector','servicios.id_servicio=ServiciosEfector.id_servicio')
                        ->where(['id_efector' => $idefector])
                        ->orderBy('nombre')->all();
        return $servicios;
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    public function getProfesion()
    {
        return $this->hasOne(Profesiones::className(), ['id_profesion' => 'id_profesion']);
    }

    public function getEspecialidad()
    {
        return $this->hasOne(Especialidades::className(), ['id_especialidad' => 'id_especialidad']);
    }

    public function getRrhhEfector()
    {
        return $this->hasMany(Rrhh_efector::className(),['id_rr_hh' => 'id_rr_hh']);
    }

    public static function buscarRrh($q)
    {
        $rrhh = Rrhh_efector::find()
            ->select(['id_rr_hh AS id', new \yii\db\Expression("CONCAT(personas.`nro_doc`, ' ', personas.`apellido`, ', ', personas.`nombre`) AS text")])
            ->join('LEF JOIN', 'personas', 
                'personas.id_persona = rr_hh_efector.id_persona AND personas.apellido LIKE "%'.$q)
            ->where(['rr_hh_efector.`id_efector`', yii::$app->user->idEfector])
            // ->orWhere(['like', 'personas.`apellido`', $q])
            // ->orWhere(['like', 'personas.`nombre`', $q])            
            ->asArray()->all();

        return $rrhh;
    }

    /**
     * Para que funcione el autcomplete en los formulario, 
     * permite buscar cualquier recurso humano de cualquier efector
    */
    // public static function Autocomplete($q)
    // {
    //     $out = ['id' => '', 'text' => ''];

    //     $query = new \yii\db\Query;
    //     $query->select(['CONCAT(personas.apellido, ", ", personas.nombre, " - ", servicios.nombre) AS text',
    //                      '`rr_hh`.id_rr_hh AS id'])
    //         ->from('rr_hh')
    //         ->where('rr_hh.id_profesion IN (2,3,4,5,6,7,8,9,10,28,29,45)')
    //         ->andWhere(['like', 'CONCAT(personas.apellido, " ", personas.nombre)', '%'.$q.'%', false])
    //         ->orWhere(['like', 'CONCAT(personas.nombre, " ", personas.apellido)', '%'.$q.'%', false])
    //         ->orWhere(['like', 'CONCAT(personas.nombre, " ", personas.otro_nombre)', '%'.$q.'%', false])
    //         ->orWhere(['like', 'CONCAT(personas.nombre, " ", personas.otro_nombre, " ", personas.apellido)', '%'.$q.'%', false])
    //         ->orWhere(['like', 'personas.nombre', '%'.$q.'%', false])
    //         ->orWhere(['like', 'personas.otro_nombre', '%'.$q.'%', false])
    //         ->orwhere(['like', 'personas.apellido', '%'.$q.'%', false])            
    //         ->join('LEFT JOIN', 'rr_hh_efector', 'rr_hh_efector.id_rr_hh = rr_hh.id_rr_hh')
    //         ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rr_hh_efector.id_servicio')
    //         ->join('LEFT JOIN', 'personas', 'rr_hh.id_persona = personas.id_persona')
    //         ->groupBy(['rr_hh_efector.id_rr_hh'])
    //         ->limit(5);
    //     $command = $query->createCommand();
        
    //     $data = $command->queryAll();

    //     $out = array_values($data);

    //     return $out;
    // } 

    public static function Autocomplete($q) {

        $out = ['id' => '', 'text' => ''];

        $query = new \yii\db\Query;
        $query->select(['CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " " ,COALESCE(personas.otro_nombre,""), " - ", servicios.nombre) AS text',
                         '`rr_hh`.id_rr_hh AS id'])
            ->from('rr_hh')
            ->where('rr_hh.id_profesion IN (2,3,4,5,6,7,8,9,10,28,29,45,49)')
            ->andWhere(['like', 'CONCAT(personas.apellido, " ", personas.nombre)', '%'.$q.'%', false])
            ->orWhere(['like', 'CONCAT(personas.nombre, " ", personas.apellido)', '%'.$q.'%', false])
            ->orWhere(['like', 'CONCAT(personas.nombre, " ", COALESCE(personas.otro_nombre,""))', '%'.$q.'%', false])
            ->orWhere(['like', 'CONCAT(personas.nombre, " ", COALESCE(personas.otro_nombre,""), " ", personas.apellido)', '%'.$q.'%', false])
            ->orWhere(['like', 'personas.nombre', '%'.$q.'%', false])
            ->orWhere(['like', 'personas.otro_nombre', '%'.$q.'%', false])
            ->orwhere(['like', 'personas.apellido', '%'.$q.'%', false])            
            ->join('LEFT JOIN', 'rr_hh_efector', 'rr_hh_efector.id_rr_hh = rr_hh.id_rr_hh')
            ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rr_hh_efector.id_servicio')
            ->join('LEFT JOIN', 'personas', 'rr_hh.id_persona = personas.id_persona')
            ->groupBy(['rr_hh_efector.id_rr_hh'])
            ->limit(5);
        $command = $query->createCommand();
       

        $data = $command->queryAll();

        $out = array_values($data);

        return $out;
    } 

    public function beforeSave($insert)
    {
        parent::beforeSave($insert);
        extract($_GET);

        $model_persona = new \common\models\Persona();
        $model_condiciones_laborales = new Condiciones_laborales();
        $model_efector = new Efector();
        $model_rr_hh_efector = new Rrhh_efector();
        
        $model_persona->load(Yii::$app->request->post());
        $model_condiciones_laborales->load(Yii::$app->request->post());
        $model_efector->load(Yii::$app->request->post());
        
             
        if ($insert) {
                if(isset($idp)){
                     $this->id_persona = $idp;
                 }else{
                     $this->id_persona = $this->id_persona ;
                 }          
            }
        return true;
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()) {

            $tsCount = \common\models\Turno::find()                
                ->where('id_rr_hh = '.$this->id_rr_hh)                
                ->count();

                //Baja logica de rr_hh
                if($tsCount > 0){
                    $rrhh = Rrhh::find()
                            ->where(['id_rr_hh' => $this->id_rr_hh])
                            ->one();
                    $rrhh->eliminado = 1;
                    $rrhh->save();
                    return false;                    
                }else{
                    return true;
                }

        } else {
            return false;
        }
    }

}
