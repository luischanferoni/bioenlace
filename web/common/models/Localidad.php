<?php

namespace common\models;

use Yii;
use common\traits\ParameterQuestionsTrait;

/**
 * This is the model class for table "localidades".
 *
 * @property integer $id_localidad
 * @property string $cod_sisa
 * @property string $cod_bahra
 * @property string $nombre
 * @property string $cod_postal
 * @property integer $id_departamento
 *
 * @property Domicilios[] $domicilios
 * @property Efectores[] $efectores
 * @property Departamentos $idDepartamento
 */
class Localidad extends \yii\db\ActiveRecord
{
    use ParameterQuestionsTrait;
   
     /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'localidades';
    }

    public $id_provincia;//ESTA PROPIEDAD FUE AGREGADA
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_localidad', 'nombre', 'cod_postal', 'id_departamento','id_provincia'], 'required'],
            //[['id_localidad', 'cod_sisa', 'cod_bahra', 'nombre', 'cod_postal', 'id_departamento'], 'required'],
            //[['id_departamento'], 'integer'],
            [['id_localidad', 'id_departamento'], 'integer'],
            //[['cod_sisa', 'cod_bahra'], 'string', 'max' => 15],
            [['nombre'], 'string', 'max' => 100],
            [['cod_postal'], 'string', 'max' => 5],
            [['cod_postal'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_localidad' => 'Localidad',
            //'cod_sisa' => 'Código Sisa',
            //'cod_bahra' => 'Código Bahra',
            'nombre' => 'Nombre',
            'cod_postal' => 'Código Postal',
            'id_departamento' => 'Departamento',
            'id_provincia' => 'Provincia',
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'ubicacion' => '¿En qué zona?',
            'localidad' => '¿En qué zona?',
            'id_localidad' => '¿En qué zona?',
            'zona' => '¿En qué zona?',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDomicilios()
    {
        return $this->hasMany(Domicilios::className(), ['id_localidad' => 'id_localidad']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfectores()
    {
        return $this->hasMany(Efectores::className(), ['id_localidad' => 'id_localidad']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDepartamento()
    {
        return $this->hasOne(Departamento::className(), ['id_departamento' => 'id_departamento']);
    }    
   
    //Esta funcion fue agregada. Se relaciona con el modelo Departamento, para obtener el nombre
    public function getDepartamentoNombre()
    {
        return $this->departamento ? $this->departamento->nombre : '- no hay departamento -';
    }
        
    public static function getLocalidadesCercanas($idLocalidad)
    {
        $sql = 'SELECT id_localidad, nombre, X(coordenadas) as latitud, Y(coordenadas) as longitud FROM localidades WHERE id_localidad = '.$idLocalidad;        
        $localidad = Yii::$app->db->createCommand($sql)->queryOne();
        
        if ($localidad['latitud'] == NULL) {
            return [$localidad];
        }

        $sql = 'SELECT id_localidad, nombre, ST_DISTANCE(coordenadas, POINT('.$localidad['latitud'].','.$localidad['longitud'].')) AS dist                     
                    FROM localidades 
                    WHERE ST_AsText(coordenadas) IS NOT NULL 
                    ORDER BY dist ASC LIMIT 5';

        $localidades = Yii::$app->db->createCommand($sql)->queryAll();

        return $localidades;
    }

    /**
     * Validar si un id_localidad existe en la base de datos
     * @param int $idLocalidad
     * @return bool
     */
    public static function validateId($idLocalidad)
    {
        try {
            $localidad = self::findOne($idLocalidad);
            return $localidad !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando id_localidad {$idLocalidad}: " . $e->getMessage(), 'localidad-model');
            return false;
        }
    }

    /**
     * Buscar localidad por nombre
     * 
     * @param string $nombre Nombre de la localidad
     * @return int|null ID de la localidad encontrada
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        
        $nombreNormalizado = trim($nombre);
        
        try {
            // Primero intentar búsqueda exacta
            $localidad = self::find()
                ->where(['nombre' => $nombreNormalizado])
                ->one();
            
            if ($localidad) {
                return (int)$localidad->id_localidad;
            }
            
            // Si no se encuentra exacto, intentar búsqueda con LIKE
            $localidad = self::find()
                ->where(['like', 'nombre', $nombreNormalizado])
                ->one();
            
            if ($localidad) {
                return (int)$localidad->id_localidad;
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando localidad por nombre '{$nombre}': " . $e->getMessage(), 'localidad-model');
        }
        
        return null;
    }

    /**
     * Extraer localidad desde el texto de la consulta del usuario
     * 
     * @param string $userQuery Texto de la consulta del usuario
     * @return int|null ID de la localidad encontrada
     */
    public static function extractFromQuery($userQuery)
    {
        // Por ahora retornamos null, la búsqueda se hace principalmente por nombre completo
        return null;
    }

    /**
     * Buscar y validar localidad desde datos extraídos y userQuery
     * 
     * @param array $extractedData Datos extraídos por la IA
     * @param string|null $userQuery Texto original de la consulta (opcional)
     * @param string|null $paramName Nombre del parámetro específico a buscar (ej: 'id_localidad', 'localidad', 'ubicacion', 'zona')
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

        // Buscar id_localidad directamente en extracted_data
        $idKeys = ['id_localidad'];
        if ($paramName && stripos($paramName, 'id_localidad') !== false) {
            array_unshift($idKeys, $paramName);
        }
        
        foreach ($idKeys as $key) {
            if (isset($extractedData[$key])) {
                $idLocalidad = $extractedData[$key];
                if (is_numeric($idLocalidad)) {
                    $result['found'] = true;
                    $result['id'] = (int)$idLocalidad;
                    $result['is_valid'] = self::validateId($result['id']);
                    if ($result['is_valid']) {
                        $localidad = self::findOne($result['id']);
                        if ($localidad) {
                            $result['name'] = $localidad->nombre;
                        }
                    }
                    return $result;
                }
            }
        }
        
        // Buscar localidad por nombre en extracted_data
        $localidadName = null;
        $searchKeys = ['localidad', 'ubicacion', 'zona'];
        if ($paramName && stripos($paramName, 'id') === false) {
            array_unshift($searchKeys, $paramName);
        }
        
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key])) {
                $localidadName = $extractedData[$key];
                break;
            }
        }
        
        // Buscar en raw data
        if ($localidadName === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['localidad'])) {
                $localidadName = $extractedData['raw']['localidad'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser localidades
                foreach ($extractedData['raw']['names'] as $name) {
                    $localidadId = self::findByName($name);
                    if ($localidadId !== null) {
                        $result['found'] = true;
                        $result['id'] = $localidadId;
                        $result['is_valid'] = true;
                        $localidad = self::findOne($localidadId);
                        if ($localidad) {
                            $result['name'] = $localidad->nombre;
                        }
                        return $result;
                    }
                }
            }
        }
        
        // Si encontramos un nombre de localidad, buscar su ID
        if ($localidadName !== null) {
            if (is_numeric($localidadName)) {
                $result['found'] = true;
                $result['id'] = (int)$localidadName;
                $result['is_valid'] = self::validateId($result['id']);
                if ($result['is_valid']) {
                    $localidad = self::findOne($result['id']);
                    if ($localidad) {
                        $result['name'] = $localidad->nombre;
                    }
                }
            } else {
                $localidadId = self::findByName($localidadName);
                if ($localidadId !== null) {
                    $result['found'] = true;
                    $result['id'] = $localidadId;
                    $result['is_valid'] = true;
                    $localidad = self::findOne($localidadId);
                    if ($localidad) {
                        $result['name'] = $localidad->nombre;
                    }
                }
            }
        }
        
        // Si aún no se encontró, buscar directamente en el texto de la consulta
        if (!$result['found'] && $userQuery !== null) {
            $localidadId = self::extractFromQuery($userQuery);
            if ($localidadId !== null) {
                $result['found'] = true;
                $result['id'] = $localidadId;
                $result['is_valid'] = true;
                $localidad = self::findOne($localidadId);
                if ($localidad) {
                    $result['name'] = $localidad->nombre;
                }
            }
        }

        return $result;
    }
    
}
