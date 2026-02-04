<?php

namespace common\models;

use common\models\Profesiones;
use common\models\Especialidades;
use common\models\Persona;
use Yii;
use common\traits\ParameterQuestionsTrait;

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
    use ParameterQuestionsTrait;
    
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
            [['id_persona', 'id_profesion', 'id_especialidad'], 'integer'],
            [['acepta_consultas_online'], 'boolean'],
            [['acepta_consultas_online'], 'default', 'value' => false],
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
            'acepta_consultas_online' => 'Acepta consultas online',
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'profesional' => '¿Con qué profesional querés el turno?',
            'id_rr_hh' => '¿Con qué profesional querés el turno?',
            'id_rrhh' => '¿Con qué profesional querés el turno?',
            'rrhh' => '¿Con qué profesional querés el turno?',
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

    /**
     * Autocomplete de RRHH usando tablas rrhh_efector y rrhh_servicio (schema actual).
     * La tabla rr_hh no existe en la base actual; id_rr_hh proviene de rrhh_efector.
     */
    public static function Autocomplete($q, $filters = []) {

        $out = ['id' => '', 'text' => ''];

        $query = new \yii\db\Query;
        $query->select([
                'CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " ", COALESCE(personas.otro_nombre,""), " - ", COALESCE(servicios.nombre, "")) AS text',
                'rrhh_efector.id_rr_hh AS id'
            ])
            ->from('rrhh_efector')
            ->join('LEFT JOIN', 'rrhh_servicio', 'rrhh_servicio.id_rr_hh = rrhh_efector.id_rr_hh AND rrhh_servicio.deleted_at IS NULL')
            ->join('LEFT JOIN', 'servicios', 'servicios.id_servicio = rrhh_servicio.id_servicio')
            ->join('LEFT JOIN', 'personas', 'rrhh_efector.id_persona = personas.id_persona')
            ->join('LEFT JOIN', 'efectores', 'rrhh_efector.id_efector = efectores.id_efector')
            ->where(['rrhh_efector.deleted_at' => null]);
        
        // Búsqueda por nombre/apellido/documento
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
        
        // Filtro por efector
        if (!empty($filters['id_efector'])) {
            $query->andWhere(['rrhh_efector.id_efector' => $filters['id_efector']]);
        }
        
        // Filtro por servicio (id_servicio o id_servicio_asignado)
        $idServicio = $filters['id_servicio'] ?? $filters['id_servicio_asignado'] ?? null;
        if (!empty($idServicio)) {
            $query->andWhere(['rrhh_servicio.id_servicio' => $idServicio]);
        }
        
        // Filtro por nombre de efector
        if (!empty($filters['efector_nombre'])) {
            $query->andWhere(['like', 'efectores.nombre', '%'.$filters['efector_nombre'].'%', false]);
        }
        
        // Filtro por nombre de servicio
        if (!empty($filters['servicio_nombre'])) {
            $query->andWhere(['like', 'servicios.nombre', '%'.$filters['servicio_nombre'].'%', false]);
        }
        
        // Agrupar para evitar duplicados (un mismo rrhh puede tener varios servicios)
        $query->groupBy(['rrhh_efector.id_rr_hh']);
        
        // Ordenamiento
        $sortBy = isset($filters['sort_by']) ? $filters['sort_by'] : 'apellido';
        $sortOrder = isset($filters['sort_order']) && strtoupper($filters['sort_order']) === 'DESC' ? SORT_DESC : SORT_ASC;
        
        switch ($sortBy) {
            case 'nombre':
                $orderBy = ['personas.nombre' => $sortOrder, 'personas.apellido' => SORT_ASC];
                break;
            case 'efector':
                $orderBy = ['efectores.nombre' => $sortOrder, 'personas.apellido' => SORT_ASC, 'personas.nombre' => SORT_ASC];
                break;
            case 'servicio':
                $orderBy = ['servicios.nombre' => $sortOrder, 'personas.apellido' => SORT_ASC, 'personas.nombre' => SORT_ASC];
                break;
            case 'apellido':
            default:
                $orderBy = ['personas.apellido' => $sortOrder, 'personas.nombre' => SORT_ASC];
                break;
        }
        
        $query->orderBy($orderBy);
        
        // Límite de resultados (por defecto 5, máximo 200)
        $limit = isset($filters['limit']) ? min((int) $filters['limit'], 200) : 5;
        $query->limit($limit);
        
        $command = $query->createCommand();
        $data = $command->queryAll();

        return array_values($data);
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

    /**
     * Validar si un id_rr_hh existe en la base de datos
     * @param int $idRrhh
     * @return bool
     */
    public static function validateId($idRrhh)
    {
        try {
            $rrhh = self::findOne($idRrhh);
            return $rrhh !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando id_rr_hh {$idRrhh}: " . $e->getMessage(), 'rrhh-model');
            return false;
        }
    }

    /**
     * Buscar rrhh por nombre (busca en la persona asociada)
     * 
     * @param string $nombre Nombre o apellido del profesional
     * @return int|null ID del rrhh encontrado
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        
        $nombreNormalizado = trim($nombre);
        
        try {
            // Buscar por nombre o apellido de la persona asociada
            $rrhh = self::find()
                ->joinWith(['idPersona'])
                ->where(['or',
                    ['like', 'personas.nombre', $nombreNormalizado],
                    ['like', 'personas.apellido', $nombreNormalizado],
                    ['like', 'personas.documento', $nombreNormalizado]
                ])
                ->one();
            
            if ($rrhh) {
                return (int)$rrhh->id_rr_hh;
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando rrhh por nombre '{$nombre}': " . $e->getMessage(), 'rrhh-model');
        }
        
        return null;
    }

    /**
     * Extraer rrhh desde el texto de la consulta del usuario
     * 
     * @param string $userQuery Texto de la consulta del usuario
     * @return int|null ID del rrhh encontrado
     */
    public static function extractFromQuery($userQuery)
    {
        // Por ahora retornamos null, la búsqueda se hace principalmente por nombre completo
        return null;
    }

    /**
     * Buscar y validar rrhh desde datos extraídos y userQuery
     * 
     * @param array $extractedData Datos extraídos por la IA
     * @param string|null $userQuery Texto original de la consulta (opcional)
     * @param string|null $paramName Nombre del parámetro específico a buscar (ej: 'id_rr_hh', 'id_rrhh', 'profesional')
     * @return array ['found' => bool, 'id' => int|null, 'name' => string|null, 'is_valid' => bool]
     */
    public static function findAndValidate($extractedData, $userQuery = null, $paramName = null)
    {
        $result = [
            'found' => false,
            'id' => null,
            'name' => null,
            'is_valid' => false,
        ];

        // Buscar id_rr_hh directamente en extracted_data
        $idKeys = ['id_rr_hh', 'id_rrhh'];
        if ($paramName) {
            array_unshift($idKeys, $paramName);
        }
        
        foreach ($idKeys as $key) {
            if (isset($extractedData[$key])) {
                $idRrhh = $extractedData[$key];
                if (is_numeric($idRrhh)) {
                    $result['found'] = true;
                    $result['id'] = (int)$idRrhh;
                    $result['is_valid'] = self::validateId($result['id']);
                    if ($result['is_valid']) {
                        $rrhh = self::findOne($result['id']);
                        if ($rrhh && $rrhh->idPersona) {
                            $result['name'] = $rrhh->idPersona->apellido . ', ' . $rrhh->idPersona->nombre;
                        }
                    }
                    return $result;
                }
            }
        }
        
        // Buscar rrhh por nombre en extracted_data
        $rrhhName = null;
        $searchKeys = ['profesional', 'rrhh'];
        if ($paramName && !in_array($paramName, $idKeys)) {
            array_unshift($searchKeys, $paramName);
        }
        
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key])) {
                $rrhhName = $extractedData[$key];
                break;
            }
        }
        
        // Buscar en raw data
        if ($rrhhName === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['profesional'])) {
                $rrhhName = $extractedData['raw']['profesional'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser profesionales
                foreach ($extractedData['raw']['names'] as $name) {
                    $rrhhId = self::findByName($name);
                    if ($rrhhId !== null) {
                        $result['found'] = true;
                        $result['id'] = $rrhhId;
                        $result['is_valid'] = true;
                        $rrhh = self::findOne($rrhhId);
                        if ($rrhh && $rrhh->idPersona) {
                            $result['name'] = $rrhh->idPersona->apellido . ', ' . $rrhh->idPersona->nombre;
                        }
                        return $result;
                    }
                }
            }
        }
        
        // Si encontramos un nombre de rrhh, buscar su ID
        if ($rrhhName !== null) {
            if (is_numeric($rrhhName)) {
                $result['found'] = true;
                $result['id'] = (int)$rrhhName;
                $result['is_valid'] = self::validateId($result['id']);
                if ($result['is_valid']) {
                    $rrhh = self::findOne($result['id']);
                    if ($rrhh && $rrhh->idPersona) {
                        $result['name'] = $rrhh->idPersona->apellido . ', ' . $rrhh->idPersona->nombre;
                    }
                }
            } else {
                $rrhhId = self::findByName($rrhhName);
                if ($rrhhId !== null) {
                    $result['found'] = true;
                    $result['id'] = $rrhhId;
                    $result['is_valid'] = true;
                    $rrhh = self::findOne($rrhhId);
                    if ($rrhh && $rrhh->idPersona) {
                        $result['name'] = $rrhh->idPersona->apellido . ', ' . $rrhh->idPersona->nombre;
                    }
                }
            }
        }
        
        // Si aún no se encontró, buscar directamente en el texto de la consulta
        if (!$result['found'] && $userQuery !== null) {
            $rrhhId = self::extractFromQuery($userQuery);
            if ($rrhhId !== null) {
                $result['found'] = true;
                $result['id'] = $rrhhId;
                $result['is_valid'] = true;
                $rrhh = self::findOne($rrhhId);
                if ($rrhh && $rrhh->idPersona) {
                    $result['name'] = $rrhh->idPersona->apellido . ', ' . $rrhh->idPersona->nombre;
                }
            }
        }

        return $result;
    }

}
