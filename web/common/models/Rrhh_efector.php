<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "rr_hh_efector".
 *
 * @property string $id_rr_hh
 * @property integer $id_efector
 * @property string $id_condicion_laboral
 * @property string $horario
 * @property integer $id_servicio
 *
 * @property CondicionesLaborales $idCondicionLaboral
 */
class Rrhh_efector extends \yii\db\ActiveRecord
{

    public $id_persona;
    public $datos;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'rr_hh_efector';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_rr_hh', 'id_efector', 'id_condicion_laboral'], 'required'],
            [['id_rr_hh', 'id_efector', 'id_condicion_laboral', 'id_servicio'], 'integer'],
            [['horario'], 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_rr_hh' => 'Id Rr Hh',
            'id_efector' => 'Id Efector',
            'id_condicion_laboral' => 'Id Condicion Laboral',
            'horario' => 'Horario',
            'id_servicio' => 'Servicio',
        ];
    }
    
    
    public function attributes(){
        return array_merge(parent::attributes(), ['efector.nombre']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdCondicionLaboral()
    {
        return $this->hasOne(Condiciones_laborales::className(), ['id_condicion_laboral' => 'id_condicion_laboral']);
    }

    public function getRrhh()
    {
            return $this->hasOne(Rrhh::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    public function getEfector()
    {
            return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }

    public function getServicio()
    {
            return $this->hasOne(Servicio::className(), ['id_servicio' => 'id_servicio']);
    }

    public function getPorEfector($id_efector){

        return Rrhh_Efector::find()->where('id_efector = '. $id_efector)->groupBy('id_servicio')->all();
    
    }

    /**
     * Devuelve un listdo de recursos humanos para un efector, 
     * ordenado por disponibilidad de turnos
     * @param type $id_efector
     * @return \yii\db\ActiveQuery
     */
    public function ordenadosPorTurno($id_efector, $id_persona){

        $and = '';
        if($id_persona){
            $and = 'AND rr_hh.id_persona = '.$id_persona;
        }
        return Turno::find()
                ->select(['COUNT(DISTINCT turnos.id_turnos) AS cant_turnos', 'rr_hh_efector.id_rr_hh', 'rr_hh_efector.id_servicio'])
                ->where(['rr_hh_efector.id_efector' => $id_efector, 'rr_hh.eliminado' => 0])
                ->andWhere('rr_hh_efector.id_servicio NOT IN (25,26,29,30,31)')
                ->join('RIGHT JOIN', 'rr_hh_efector', 
                            'rr_hh_efector.id_rr_hh = turnos.id_rr_hh AND rr_hh_efector.id_efector = turnos.id_efector AND fecha >= CURRENT_DATE()')
                ->join('RIGHT JOIN', 'rr_hh', 'rr_hh.id_rr_hh = rr_hh_efector.id_rr_hh '.$and)
                ->groupBy(['rr_hh_efector.id_rr_hh'])
                ->orderBy('rr_hh_efector.id_servicio, cant_turnos')
                ->all();         

    }

    /*
    * TODO: modificar este para que reciba los id por parametro | hacer un mapeo correcto entre ids y los nombres de los servicios
    */
    public static function obtenerProfesionalesPorEfector($id_efector){

        return Rrhh_Efector::find()
            ->select(['rr_hh_efector.id_rr_hh', 'rr_hh_efector.id_servicio', 'personas.id_persona', 'CONCAT(personas.apellido, ", ", personas.nombre, " - ", servicios.nombre) AS datos'])
            ->where(['rr_hh_efector.id_efector' => $id_efector])
            //->andWhere('rr_hh.id_profesion IN (2,4,5,6,7,8,9,10,29)')
            ->joinWith('rrhh')
            ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rr_hh_efector.id_servicio')
            ->join('LEFT JOIN', 'personas', 'rr_hh.id_persona = personas.id_persona')                
            ->groupBy(['rr_hh_efector.id_rr_hh'])
            ->orderBy('rr_hh_efector.id_servicio')
            ->all();
    }    
    /*
    * Se obtienen los profesionales medicos y odontologos
    */
    public function obtenerMedicosPorEfector($id_efector){
        $ids = [6,7,8];
        return Rrhh_Efector::find()
            ->select(['rr_hh_efector.id_rr_hh', 'rr_hh_efector.id_servicio', 'personas.id_persona', 'CONCAT(personas.apellido, ", ", personas.nombre, " - ", servicios.nombre) AS datos'])
            ->where(['rr_hh_efector.id_efector' => $id_efector])
            ->andWhere(['rr_hh.id_profesion' => $ids])
            ->joinWith('rrhh')
            ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rr_hh_efector.id_servicio')
            ->join('LEFT JOIN', 'personas', 'rr_hh.id_persona = personas.id_persona')                
            ->groupBy(['rr_hh_efector.id_rr_hh'])
            ->orderBy('rr_hh_efector.id_servicio')
            ->all();

    }
}
