<?php

namespace common\models;

use Yii;
use common\models\ServiciosEfector;
use common\traits\ParameterQuestionsTrait;

/**
 * This is the model class for table "efectores".
 *
 * @property integer $id_efector
 * @property string $codigo_sisa
 * @property string $nombre
 * @property string $dependencia
 * @property string $tipologia
 * @property string $domicilio
 * @property string $telefono
 * @property string $origen_financiamiento
 * @property integer $id_localidad
 * @property string $estado
 *
 * @property AgendaRrhh[] $agendaRrhhs
 * @property Localidades $idLocalidad
 * @property ServiciosEfector[] $serviciosEfectors
 * @property Servicios[] $idServicios
 * @property Turnos[] $turnos
 */
class Efector extends \yii\db\ActiveRecord
{
    use ParameterQuestionsTrait;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'efectores';
        
    }
    
    public $id_departamento;//este atributo agrego

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['codigo_sisa', 'nombre', 'dependencia', 'tipologia', 'domicilio', 'origen_financiamiento', 'id_localidad'], 'required'],
            [['id_localidad'], 'integer'],
            [['estado','grupo','formas_acceso','telefono','telefono2','telefono3','mail1',
              'mail2','mail3','dias_horario'], 'string'],
            [['codigo_sisa'], 'string', 'max' => 15],
            [['nombre', 'domicilio'], 'string', 'max' => 100],
            [['dependencia', 'origen_financiamiento'], 'string', 'max' => 40],
            [['tipologia'], 'string', 'max' => 10],
            [['telefono'], 'string', 'max' => 50],
            
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_efector' => 'Codigo del Efector',
            'codigo_sisa' => 'Código SISA',
            'nombre' => 'Nombre',
            'dependencia' => 'Dependencia',
            'tipologia' => 'Tipologia',
            'domicilio' => 'Domicilio',
            'formas_acceso' => 'Como Llegar',
            'grupo' => 'Grupo',
            'dias_horario' => 'Horarios de Atencion',
            'telefono' => 'Numero de Telefono',
            'telefono2' => 'Numero de Telefono 2',
            'telefono3' => 'Numero de Telefono 3',
            'mail1' => 'Correo Electronico',
            'mail2' => 'Correo Electronico 2',
            'mail3' => 'Correo Electronico 3',
            'origen_financiamiento' => 'Origen del financiamiento',
            'id_localidad' => 'Localidad',            
            'id_departamento'=>'Departamento',
            'estado' => 'Estado: Activo o Inactivo',
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'efector' => '¿En qué centro de salud?',
            'id_efector' => '¿En qué centro de salud?',
            'centro_salud' => '¿En qué centro de salud?',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAgendaRrhhs()
    {
        return $this->hasMany(Agenda_Rrhh::className(), ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLocalidad()
    {
        return $this->hasOne(Localidad::className(), ['id_localidad' => 'id_localidad']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRrHhEfectors()
    {
        return $this->hasMany(RrHh_Efector::className(), ['id_efector' => 'id_efector']);
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getServiciosEfectors()
    {
        return $this->hasMany(ServiciosEfector::className(), ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdServicios()
    {
        return $this->hasMany(Servicio::className(), ['id_servicio' => 'id_servicio'])->viaTable('ServiciosEfector', ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTurnos()
    {
        return $this->hasMany(Turno::className(), ['id_efector_referencia' => 'id_efector']);
    }
    
    //Esta funcion fue agregada, se relaciona con el modelo Localidad para obtener el nombre
    public function getLocalidadNombre()
    {
        return $this->idLocalidad ? $this->idLocalidad->nombre : '- no hay localidad -';
    }
  
      /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdRrHhs()
    {
        return $this->hasMany(RrHh::className(), ['id_rr_hh' => 'id_rr_hh'])->viaTable('rr_hh_efector', ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserEfectors()
    {
        return $this->hasMany(UserEfector::className(), ['id_efector' => 'id_efector']);
        
        
    }
    
    public function getEfectoresImplementados()
    {
        $efectores = self::find()->asArray()->select(['id_efector' => 'id_efector', 'nombre' => 'nombre'])
                        ->from('efectores')
                        ->where(['implementado' => 'V'])
                        ->orderBy('nombre')->all();
        return $efectores;
    }

    public static function getTodosLosEfectores()
    {
        $efectores = self::find()->asArray()->select(['id_efector' => 'id_efector', 'nombre' => 'nombre'])
                        ->from('efectores')
                        ->orderBy('nombre')->all();
        return $efectores;
    }

    public static function liveSearch($q, $filters = [])
    {
        $query = self::find()
                ->select(['efectores.id_efector AS id', 'efectores.nombre AS text'])
                ->distinct();
        
        // Búsqueda por nombre
        if (!empty($q)) {
            $query->andWhere(['like', 'efectores.nombre', '%'.$q.'%', false]);
        }
        
        // Filtro por localidad
        if (!empty($filters['id_localidad'])) {
            $query->andWhere(['efectores.id_localidad' => $filters['id_localidad']]);
        }
        
        // Filtro por departamento
        if (!empty($filters['id_departamento'])) {
            $query->joinWith(['localidad.departamento'])
                  ->andWhere(['departamentos.id_departamento' => $filters['id_departamento']]);
        }
        
        // Filtro por servicio
        if (!empty($filters['id_servicio'])) {
            $query->joinWith(['serviciosEfectors'])
                  ->andWhere(['servicios_efector.id_servicio' => $filters['id_servicio']]);
        }
        
        // Filtro por dependencia
        if (!empty($filters['dependencia'])) {
            $query->andWhere(['like', 'efectores.dependencia', $filters['dependencia']]);
        }
        
        // Filtro por tipología
        if (!empty($filters['tipologia'])) {
            $query->andWhere(['efectores.tipologia' => $filters['tipologia']]);
        }
        
        // Filtro por estado
        if (!empty($filters['estado'])) {
            $query->andWhere(['efectores.estado' => $filters['estado']]);
        }
        
        // Filtro por geolocalización (radio desde coordenadas)
        if (!empty($filters['latitud']) && !empty($filters['longitud']) && !empty($filters['radio_km'])) {
            $lat = floatval($filters['latitud']);
            $lng = floatval($filters['longitud']);
            $radio = floatval($filters['radio_km']);
            
            // Usar fórmula de Haversine para calcular distancia
            $query->joinWith(['localidad'])
                  ->andWhere('localidades.coordenadas IS NOT NULL')
                  ->andWhere("(
                    ST_DISTANCE_SPHERE(
                        localidades.coordenadas, 
                        POINT({$lng}, {$lat})
                    ) / 1000
                  ) <= {$radio}");
        }
        
        // Filtro por nombre de localidad
        if (!empty($filters['localidad_nombre'])) {
            $query->joinWith(['localidad'])
                  ->andWhere(['like', 'localidades.nombre', '%'.$filters['localidad_nombre'].'%', false]);
        }
        
        // Filtro por nombre de departamento
        if (!empty($filters['departamento_nombre'])) {
            $query->joinWith(['localidad.departamento'])
                  ->andWhere(['like', 'departamentos.nombre', '%'.$filters['departamento_nombre'].'%', false]);
        }
        
        // Ordenamiento
        $sortBy = isset($filters['sort_by']) ? $filters['sort_by'] : 'nombre';
        $sortOrder = isset($filters['sort_order']) && strtoupper($filters['sort_order']) === 'DESC' ? SORT_DESC : SORT_ASC;
        
        switch ($sortBy) {
            case 'localidad':
                $query->joinWith(['localidad']); // Yii2 maneja joins duplicados automáticamente
                $orderBy = ['localidades.nombre' => $sortOrder];
                break;
            case 'departamento':
                $query->joinWith(['localidad.departamento']); // Yii2 maneja joins duplicados automáticamente
                $orderBy = ['departamentos.nombre' => $sortOrder];
                break;
            case 'dependencia':
                $orderBy = ['efectores.dependencia' => $sortOrder, 'efectores.nombre' => SORT_ASC];
                break;
            case 'tipologia':
                $orderBy = ['efectores.tipologia' => $sortOrder, 'efectores.nombre' => SORT_ASC];
                break;
            case 'estado':
                $orderBy = ['efectores.estado' => $sortOrder, 'efectores.nombre' => SORT_ASC];
                break;
            case 'distancia':
                // Solo aplicable si hay geolocalización
                if (!empty($filters['latitud']) && !empty($filters['longitud'])) {
                    $lat = floatval($filters['latitud']);
                    $lng = floatval($filters['longitud']);
                    $query->joinWith(['localidad']); // Yii2 maneja joins duplicados automáticamente
                    $query->addSelect([
                        'distancia' => new \yii\db\Expression("(
                            ST_DISTANCE_SPHERE(
                                localidades.coordenadas, 
                                POINT({$lng}, {$lat})
                            ) / 1000
                        )")
                    ]);
                    $orderBy = ['distancia' => $sortOrder];
                } else {
                    $orderBy = ['efectores.nombre' => $sortOrder];
                }
                break;
            case 'nombre':
            default:
                $orderBy = ['efectores.nombre' => $sortOrder];
                break;
        }
        
        $query->orderBy($orderBy);
        
        // Límite de resultados (por defecto 5, máximo 200)
        $limit = isset($filters['limit']) ? min(intval($filters['limit']), 200) : 5;
        $query->limit($limit);
        
        $results = $query->asArray()->all();
        
        return $results;
    }

    /**
     * Validar si un id_efector existe en la base de datos
     * @param int $idEfector
     * @return bool
     */
    public static function validateId($idEfector)
    {
        try {
            $efector = self::findOne($idEfector);
            return $efector !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando id_efector {$idEfector}: " . $e->getMessage(), 'efector-model');
            return false;
        }
    }

    /**
     * Buscar efector por nombre
     * 
     * @param string $nombre Nombre del efector
     * @return int|null ID del efector encontrado
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        
        $nombreNormalizado = trim($nombre);
        
        try {
            // Primero intentar búsqueda exacta
            $efector = self::find()
                ->where(['nombre' => $nombreNormalizado])
                ->one();
            
            if ($efector) {
                return (int)$efector->id_efector;
            }
            
            // Si no se encuentra exacto, intentar búsqueda con LIKE
            $efector = self::find()
                ->where(['LIKE', 'nombre', $nombreNormalizado])
                ->one();
            
            if ($efector) {
                return (int)$efector->id_efector;
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando efector por nombre '{$nombre}': " . $e->getMessage(), 'efector-model');
        }
        
        return null;
    }

    /**
     * Extraer efector desde el texto de la consulta del usuario
     * 
     * @param string $userQuery Texto de la consulta del usuario
     * @return int|null ID del efector encontrado
     */
    public static function extractFromQuery($userQuery)
    {
        if (empty($userQuery) || !is_string($userQuery)) {
            return null;
        }
        
        // Buscar nombres de efectores en el texto usando búsqueda parcial
        // Esto es más complejo que servicios, así que por ahora retornamos null
        // y dejamos que se busque por nombre completo desde extracted_data
        return null;
    }

    /**
     * Buscar y validar efector desde datos extraídos y userQuery
     * 
     * @param array $extractedData Datos extraídos por la IA
     * @param string|null $userQuery Texto original de la consulta (opcional)
     * @param string|null $paramName Nombre del parámetro específico a buscar (ej: 'id_efector', 'efector')
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

        // Buscar id_efector directamente en extracted_data
        if (isset($extractedData['id_efector'])) {
            $idEfector = $extractedData['id_efector'];
            if (is_numeric($idEfector)) {
                $result['found'] = true;
                $result['id'] = (int)$idEfector;
                $result['is_valid'] = self::validateId($result['id']);
                if ($result['is_valid']) {
                    $efector = self::findOne($result['id']);
                    if ($efector) {
                        $result['name'] = $efector->nombre;
                    }
                }
                return $result;
            }
        }
        
        // Buscar efector por nombre en extracted_data
        $efectorName = null;
        $searchKeys = ['efector', 'centro_salud'];
        if ($paramName) {
            array_unshift($searchKeys, $paramName);
        }
        
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key])) {
                $efectorName = $extractedData[$key];
                break;
            }
        }
        
        // Buscar en raw data
        if ($efectorName === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['efector'])) {
                $efectorName = $extractedData['raw']['efector'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser efectores
                foreach ($extractedData['raw']['names'] as $name) {
                    $efectorId = self::findByName($name);
                    if ($efectorId !== null) {
                        $result['found'] = true;
                        $result['id'] = $efectorId;
                        $result['is_valid'] = true;
                        $efector = self::findOne($efectorId);
                        if ($efector) {
                            $result['name'] = $efector->nombre;
                        }
                        return $result;
                    }
                }
            }
        }
        
        // Si encontramos un nombre de efector, buscar su ID
        if ($efectorName !== null) {
            if (is_numeric($efectorName)) {
                $result['found'] = true;
                $result['id'] = (int)$efectorName;
                $result['is_valid'] = self::validateId($result['id']);
            } else {
                $efectorId = self::findByName($efectorName);
                if ($efectorId !== null) {
                    $result['found'] = true;
                    $result['id'] = $efectorId;
                    $result['is_valid'] = true;
                    $efector = self::findOne($efectorId);
                    if ($efector) {
                        $result['name'] = $efector->nombre;
                    }
                }
            }
        }
        
        // Si aún no se encontró, buscar directamente en el texto de la consulta
        if (!$result['found'] && $userQuery !== null) {
            $efectorId = self::extractFromQuery($userQuery);
            if ($efectorId !== null) {
                $result['found'] = true;
                $result['id'] = $efectorId;
                $result['is_valid'] = true;
                $efector = self::findOne($efectorId);
                if ($efector) {
                    $result['name'] = $efector->nombre;
                }
            }
        }

        return $result;
    }

}
