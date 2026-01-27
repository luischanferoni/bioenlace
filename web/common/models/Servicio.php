<?php

namespace common\models;

use Yii;
use common\models\Efector;
use common\traits\ParameterQuestionsTrait;

/**
 * This is the model class for table "servicios".
 *
 * @property string $id_servicio
 * @property string $nombre
 *
 * @property Referencia[] $referencias
 * @property ServiciosEfector[] $serviciosEfectors
 * @property Efectores[] $idEfectors
 * @property Turnos[] $turnos
 */
class Servicio extends \yii\db\ActiveRecord
{
    use ParameterQuestionsTrait;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'servicios';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 40],
            [['acepta_turnos', 'acepta_practicas', 'parametros', 'item_name'], 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_servicio' => 'Codigo de servicio',
            'nombre' => 'Nombre del serivicio',
            'acepta_turnos' => 'Acepta Agenda',
            'acepta_practicas' => 'Acepta Practicas',
            'item_name' => 'Rol'
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'servicio' => '¿Qué servicio necesitás?',
            'id_servicio' => '¿Qué servicio necesitás?',
            'servicio_asignado' => '¿Qué servicio necesitás?',
        ];
    }
    
    public function getRrhhs()
    {
        return $this->hasMany(Rrhh::className(), ['id_rr_hh' => 'id_rr_hh'])
                ->viaTable('rr_hh_efector', ['id_servicio' => 'id_servicio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReferencias()
    {
        return $this->hasMany(Referencia::className(), ['id_servicio' => 'id_servicio']);
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getServiciosEfectors()
    {
        return $this->hasMany(ServiciosEfector::className(), ['id_servicio' => 'id_servicio']);
}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdEfectors()
    {
        return $this->hasMany(Efectores::className(), ['id_efector' => 'id_efector'])->viaTable('ServiciosEfector', ['id_servicio' => 'id_servicio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTurnos()
    {
        return $this->hasMany(Turnos::className(), ['id_servicio' => 'id_servicio']);
    }
    
    public function getServiciosPorEfector($id) 
    {
        $servicios=Departamento::find()->asArray()
                ->select(['id' => 's.id_servicio', 'name' => 's.nombre'])
                ->from('servicios s')
                ->innerJoin('ServiciosEfector se', 's.id_servicio = se.id_servicio')
                ->where(['se.id_efector' => $id])->all();
        return $servicios;
    }

    public function getEfector()
    {
        return $this->hasMany(Efector::className(), ['id_efector' => 'id_efector'])
            ->viaTable('ServiciosEfector', ['id_servicio' => 'id_servicio']);
    }

    public static function searchServicio($q)
    {
        $results = Servicio::find()
                ->select(['id_servicio AS id', 'nombre AS text'])
                ->where(['like', 'nombre', '%'.$q.'%', false])
                ->asArray()
                ->all();

        return $results;
    }

    public static function puedeAtender($id_servicio){

        $servicio = self::find()->where(['id_servicio'=>$id_servicio])->one();

        if($servicio->item_name == 'Medico' || $servicio->item_name == 'enfermeria'){
            return true;
        }

        return false;

    }

    /**
     * Validar si un id_servicio existe en la base de datos
     * @param int $idServicio
     * @return bool
     */
    public static function validateId($idServicio)
    {
        try {
            $servicio = self::findOne($idServicio);
            return $servicio !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando id_servicio {$idServicio}: " . $e->getMessage(), 'servicio-model');
            return false;
        }
    }

    /**
     * Buscar servicio por nombre (soporta búsqueda parcial y sinónimos)
     * 
     * @param string $nombre Nombre del servicio (ej: "odontologo", "odontología", "ODONTOLOGIA")
     * @return int|null ID del servicio encontrado
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        
        // Normalizar nombre: convertir a mayúsculas y limpiar
        $nombreNormalizado = strtoupper(trim($nombre));
        
        // Mapeo de sinónimos comunes
        $sinonimos = [
            'odontologo' => 'ODONTOLOGIA',
            'odontología' => 'ODONTOLOGIA',
            'odontologia' => 'ODONTOLOGIA',
            'dental' => 'ODONTOLOGIA',
            'dentista' => 'ODONTOLOGIA',
            'pediatra' => 'PEDIATRIA',
            'pediatría' => 'PEDIATRIA',
            'ginecologo' => 'GINECOLOGIA',
            'ginecología' => 'GINECOLOGIA',
            'ginecologia' => 'GINECOLOGIA',
            'medico' => 'MED GENERAL',
            'médico' => 'MED GENERAL',
            'medico general' => 'MED GENERAL',
            'medico familiar' => 'MED FAMILIAR',
            'medico clinica' => 'MED CLINICA',
            'médico clínica' => 'MED CLINICA',
            'clinica' => 'MED CLINICA',
            'clínica' => 'MED CLINICA',
            'psicologo' => 'PSICOLOGIA',
            'psicología' => 'PSICOLOGIA',
            'psicologia' => 'PSICOLOGIA',
            'kinesiologo' => 'KINESIOLOGIA',
            'kinesiología' => 'KINESIOLOGIA',
            'kinesiologia' => 'KINESIOLOGIA',
            'kinesio' => 'KINESIOLOGIA',
        ];
        
        // Verificar si hay un sinónimo directo
        $nombreLower = strtolower($nombreNormalizado);
        if (isset($sinonimos[$nombreLower])) {
            $nombreNormalizado = $sinonimos[$nombreLower];
        }
        
        try {
            // Primero intentar búsqueda exacta
            $servicio = self::find()
                ->where(['nombre' => $nombreNormalizado])
                ->one();
            
            if ($servicio) {
                return (int)$servicio->id_servicio;
            }
            
            // Si no se encuentra exacto, intentar búsqueda con LIKE
            $servicio = self::find()
                ->where(['LIKE', 'nombre', $nombreNormalizado])
                ->one();
            
            if ($servicio) {
                return (int)$servicio->id_servicio;
            }
            
            // Último intento: buscar sinónimos en la base de datos
            foreach ($sinonimos as $sinonimo => $nombreServicio) {
                if (stripos($nombreNormalizado, $sinonimo) !== false || stripos($sinonimo, $nombreNormalizado) !== false) {
                    $servicio = self::find()
                        ->where(['nombre' => $nombreServicio])
                        ->one();
                    
                    if ($servicio) {
                        return (int)$servicio->id_servicio;
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando servicio por nombre '{$nombre}': " . $e->getMessage(), 'servicio-model');
        }
        
        return null;
    }

    /**
     * Extraer servicio desde el texto de la consulta del usuario
     * Busca palabras clave de servicios comunes en el texto
     * 
     * @param string $userQuery Texto de la consulta del usuario
     * @return int|null ID del servicio encontrado
     */
    public static function extractFromQuery($userQuery)
    {
        if (empty($userQuery) || !is_string($userQuery)) {
            return null;
        }
        
        $queryLower = strtolower(trim($userQuery));
        
        // Palabras clave de servicios comunes
        $servicioKeywords = [
            'odontologo', 'odontología', 'odontologia', 'dental', 'dentista',
            'pediatra', 'pediatría',
            'ginecologo', 'ginecología', 'ginecologia',
            'medico', 'médico', 'medico general', 'medico familiar', 'medico clinica', 'médico clínica', 'clinica', 'clínica',
            'psicologo', 'psicología', 'psicologia',
            'kinesiologo', 'kinesiología', 'kinesiologia', 'kinesio',
        ];
        
        // Buscar cada palabra clave en el texto
        foreach ($servicioKeywords as $keyword) {
            if (stripos($queryLower, $keyword) !== false) {
                $servicioId = self::findByName($keyword);
                if ($servicioId !== null) {
                    return $servicioId;
                }
            }
        }
        
        return null;
    }

    /**
     * Buscar y validar servicio desde datos extraídos y userQuery
     * Busca en extractedData primero, luego en userQuery si no se encuentra
     * 
     * @param array $extractedData Datos extraídos por la IA
     * @param string|null $userQuery Texto original de la consulta (opcional)
     * @param string|null $paramName Nombre del parámetro específico a buscar (ej: 'id_servicio', 'servicio_actual')
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

        // Buscar id_servicio directamente en extracted_data
        if (isset($extractedData['id_servicio'])) {
            $idServicio = $extractedData['id_servicio'];
            if (is_numeric($idServicio)) {
                $result['found'] = true;
                $result['id'] = (int)$idServicio;
                $result['is_valid'] = self::validateId($result['id']);
                if ($result['is_valid']) {
                    $servicio = self::findOne($result['id']);
                    if ($servicio) {
                        $result['name'] = $servicio->nombre;
                    }
                }
                return $result;
            }
        }
        
        // Buscar servicio por nombre en extracted_data
        $servicioName = null;
        $searchKeys = ['servicio', 'servicio_actual'];
        if ($paramName) {
            array_unshift($searchKeys, $paramName);
        }
        
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key])) {
                $servicioName = $extractedData[$key];
                break;
            }
        }
        
        // Buscar en raw data
        if ($servicioName === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['servicio'])) {
                $servicioName = $extractedData['raw']['servicio'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser servicios
                foreach ($extractedData['raw']['names'] as $name) {
                    $servicioId = self::findByName($name);
                    if ($servicioId !== null) {
                        $result['found'] = true;
                        $result['id'] = $servicioId;
                        $result['is_valid'] = true;
                        $servicio = self::findOne($servicioId);
                        if ($servicio) {
                            $result['name'] = $servicio->nombre;
                        }
                        return $result;
                    }
                }
            }
        }
        
        // Si encontramos un nombre de servicio, buscar su ID
        if ($servicioName !== null) {
            if (is_numeric($servicioName)) {
                $result['found'] = true;
                $result['id'] = (int)$servicioName;
                $result['is_valid'] = self::validateId($result['id']);
            } else {
                $servicioId = self::findByName($servicioName);
                if ($servicioId !== null) {
                    $result['found'] = true;
                    $result['id'] = $servicioId;
                    $result['is_valid'] = true;
                    $servicio = self::findOne($servicioId);
                    if ($servicio) {
                        $result['name'] = $servicio->nombre;
                    }
                }
            }
        }
        
        // Si aún no se encontró, buscar directamente en el texto de la consulta
        if (!$result['found'] && $userQuery !== null) {
            $servicioId = self::extractFromQuery($userQuery);
            if ($servicioId !== null) {
                $result['found'] = true;
                $result['id'] = $servicioId;
                $result['is_valid'] = true;
                $servicio = self::findOne($servicioId);
                if ($servicio) {
                    $result['name'] = $servicio->nombre;
                }
            }
        }

        return $result;
    }


}