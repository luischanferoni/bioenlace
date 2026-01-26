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

    public static function Autocomplete($q, $filters = []) {

        $out = ['id' => '', 'text' => ''];

        $query = new \yii\db\Query;
        $query->select(['CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " " ,COALESCE(personas.otro_nombre,""), " - ", COALESCE(servicios.nombre, "")) AS text',
                         'rr_hh.id_rr_hh AS id'])
            ->from('rr_hh')
            ->where('rr_hh.id_profesion IN (2,3,4,5,6,7,8,9,10,28,29,45,49)')
            ->join('LEFT JOIN', 'rr_hh_efector', 'rr_hh_efector.id_rr_hh = rr_hh.id_rr_hh AND rr_hh_efector.deleted_at IS NULL')
            ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rr_hh_efector.id_servicio')
            ->join('LEFT JOIN', 'personas', 'rr_hh.id_persona = personas.id_persona')
            ->join('LEFT JOIN', 'profesiones', 'rr_hh.id_profesion = profesiones.id_profesion')
            ->join('LEFT JOIN', 'especialidades', 'rr_hh.id_especialidad = especialidades.id_especialidad')
            ->join('LEFT JOIN', 'efectores', 'rr_hh_efector.id_efector = efectores.id_efector');
        
        // Búsqueda por nombre/apellido
        if (!empty($q)) {
            $query->andWhere([
                'or',
                ['like', 'CONCAT(personas.apellido, " ", personas.nombre)', '%'.$q.'%', false],
                ['like', 'CONCAT(personas.nombre, " ", personas.apellido)', '%'.$q.'%', false],
                ['like', 'CONCAT(personas.nombre, " ", COALESCE(personas.otro_nombre,""))', '%'.$q.'%', false],
                ['like', 'CONCAT(personas.nombre, " ", COALESCE(personas.otro_nombre,""), " ", personas.apellido)', '%'.$q.'%', false],
                ['like', 'personas.nombre', '%'.$q.'%', false],
                ['like', 'personas.otro_nombre', '%'.$q.'%', false],
                ['like', 'personas.apellido', '%'.$q.'%', false],
                ['like', 'personas.documento', '%'.$q.'%', false]
            ]);
        }
        
        // Filtro por profesión
        if (!empty($filters['id_profesion'])) {
            $query->andWhere(['rr_hh.id_profesion' => $filters['id_profesion']]);
        }
        
        // Filtro por especialidad
        if (!empty($filters['id_especialidad'])) {
            $query->andWhere(['rr_hh.id_especialidad' => $filters['id_especialidad']]);
        }
        
        // Filtro por efector
        if (!empty($filters['id_efector'])) {
            $query->andWhere(['rr_hh_efector.id_efector' => $filters['id_efector']]);
        }
        
        // Filtro por servicio
        if (!empty($filters['id_servicio'])) {
            $query->andWhere(['rr_hh_efector.id_servicio' => $filters['id_servicio']]);
        }
        
        // Filtro por nombre de profesión
        if (!empty($filters['profesion_nombre'])) {
            $query->andWhere(['like', 'profesiones.nombre', '%'.$filters['profesion_nombre'].'%', false]);
        }
        
        // Filtro por nombre de especialidad
        if (!empty($filters['especialidad_nombre'])) {
            $query->andWhere(['like', 'especialidades.nombre', '%'.$filters['especialidad_nombre'].'%', false]);
        }
        
        // Filtro por nombre de efector
        if (!empty($filters['efector_nombre'])) {
            $query->andWhere(['like', 'efectores.nombre', '%'.$filters['efector_nombre'].'%', false]);
        }
        
        // Filtro por nombre de servicio
        if (!empty($filters['servicio_nombre'])) {
            $query->andWhere(['like', 'servicios.nombre', '%'.$filters['servicio_nombre'].'%', false]);
        }
        
        // Agrupar para evitar duplicados
        $query->groupBy(['rr_hh.id_rr_hh']);
        
        // Ordenamiento
        $sortBy = isset($filters['sort_by']) ? $filters['sort_by'] : 'apellido';
        $sortOrder = isset($filters['sort_order']) && strtoupper($filters['sort_order']) === 'DESC' ? SORT_DESC : SORT_ASC;
        
        switch ($sortBy) {
            case 'nombre':
                $orderBy = ['personas.nombre' => $sortOrder, 'personas.apellido' => SORT_ASC];
                break;
            case 'apellido':
                $orderBy = ['personas.apellido' => $sortOrder, 'personas.nombre' => SORT_ASC];
                break;
            case 'profesion':
                $orderBy = ['profesiones.nombre' => $sortOrder, 'personas.apellido' => SORT_ASC, 'personas.nombre' => SORT_ASC];
                break;
            case 'especialidad':
                $orderBy = ['especialidades.nombre' => $sortOrder, 'personas.apellido' => SORT_ASC, 'personas.nombre' => SORT_ASC];
                break;
            case 'efector':
                $orderBy = ['efectores.nombre' => $sortOrder, 'personas.apellido' => SORT_ASC, 'personas.nombre' => SORT_ASC];
                break;
            case 'servicio':
                $orderBy = ['servicios.nombre' => $sortOrder, 'personas.apellido' => SORT_ASC, 'personas.nombre' => SORT_ASC];
                break;
            default:
                $orderBy = ['personas.apellido' => $sortOrder, 'personas.nombre' => SORT_ASC];
                break;
        }
        
        $query->orderBy($orderBy);
        
        // Límite de resultados (por defecto 5, máximo 200)
        $limit = isset($filters['limit']) ? min(intval($filters['limit']), 200) : 5;
        $query->limit($limit);
        
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
