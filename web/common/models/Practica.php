<?php

namespace common\models;

use Yii;
use common\traits\ParameterQuestionsTrait;

/**
 * This is the model class for table "practicas".
 *
 * @property integer $id_practica
 * @property string $codigo_practica
 * @property integer $id_categoria
 * @property string $nombre
 * @property string $observacion
 * @property string $arancel
 *
 * @property DetallePracticas[] $detallePracticas
 * @property CategoriasPracticas $idCategoria
 */
class Practica extends \yii\db\ActiveRecord
{
    use ParameterQuestionsTrait;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'practicas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_practica'], 'required'],
            [['id_practica', 'id_categoria'], 'integer'],
            [['observacion'], 'string'],
            [['arancel'], 'number'],
            [['codigo_practica'], 'string', 'max' => 10],
            [['nombre'], 'string', 'max' => 200]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_practica' => 'Id Practica',
            'codigo_practica' => 'Codigo Practica',
            'id_categoria' => 'Id Categoria',
            'nombre' => 'Nombre',
            'observacion' => 'Observacion',
            'arancel' => 'Arancel',
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'tipo_practica' => '¿Qué tipo de estudio necesitás?',
            'practica' => '¿Qué tipo de estudio necesitás?',
            'id_practica' => '¿Qué tipo de estudio necesitás?',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDetallePracticas()
    {
        return $this->hasMany(DetallePracticas::className(), ['codigo_practica' => 'codigo_practica']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdCategoria()
    {
        return $this->hasOne(CategoriasPracticas::className(), ['id_categoria' => 'id_categoria']);
    }

    /**
     * Validar si un id_practica existe en la base de datos
     * @param int $idPractica
     * @return bool
     */
    public static function validateId($idPractica)
    {
        try {
            $practica = self::findOne($idPractica);
            return $practica !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando id_practica {$idPractica}: " . $e->getMessage(), 'practica-model');
            return false;
        }
    }

    /**
     * Buscar práctica por nombre
     * 
     * @param string $nombre Nombre de la práctica
     * @return int|null ID de la práctica encontrada
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        
        $nombreNormalizado = trim($nombre);
        
        try {
            // Buscar por nombre
            $practica = self::find()
                ->where(['like', 'nombre', $nombreNormalizado])
                ->one();
            
            if ($practica) {
                return (int)$practica->id_practica;
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando práctica por nombre '{$nombre}': " . $e->getMessage(), 'practica-model');
        }
        
        return null;
    }

    /**
     * Extraer práctica desde el texto de la consulta del usuario
     * 
     * @param string $userQuery Texto de la consulta del usuario
     * @return int|null ID de la práctica encontrada
     */
    public static function extractFromQuery($userQuery)
    {
        // Por ahora retornamos null, la búsqueda se hace principalmente por nombre completo
        return null;
    }

    /**
     * Buscar y validar práctica desde datos extraídos y userQuery
     * 
     * @param array $extractedData Datos extraídos por la IA
     * @param string|null $userQuery Texto original de la consulta (opcional)
     * @param string|null $paramName Nombre del parámetro específico a buscar (ej: 'id_practica', 'practica', 'tipo_practica')
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

        // Buscar id_practica directamente en extracted_data
        $idKeys = ['id_practica'];
        if ($paramName && stripos($paramName, 'id') !== false) {
            array_unshift($idKeys, $paramName);
        }
        
        foreach ($idKeys as $key) {
            if (isset($extractedData[$key])) {
                $idPractica = $extractedData[$key];
                if (is_numeric($idPractica)) {
                    $result['found'] = true;
                    $result['id'] = (int)$idPractica;
                    $result['is_valid'] = self::validateId($result['id']);
                    if ($result['is_valid']) {
                        $practica = self::findOne($result['id']);
                        if ($practica) {
                            $result['name'] = $practica->nombre;
                        }
                    }
                    return $result;
                }
            }
        }
        
        // Buscar práctica por nombre en extracted_data
        $practicaName = null;
        $searchKeys = ['practica', 'tipo_practica'];
        if ($paramName && stripos($paramName, 'id') === false) {
            array_unshift($searchKeys, $paramName);
        }
        
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key])) {
                $practicaName = $extractedData[$key];
                break;
            }
        }
        
        // Buscar en raw data
        if ($practicaName === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['practica'])) {
                $practicaName = $extractedData['raw']['practica'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser prácticas
                foreach ($extractedData['raw']['names'] as $name) {
                    $practicaId = self::findByName($name);
                    if ($practicaId !== null) {
                        $result['found'] = true;
                        $result['id'] = $practicaId;
                        $result['is_valid'] = true;
                        $practica = self::findOne($practicaId);
                        if ($practica) {
                            $result['name'] = $practica->nombre;
                        }
                        return $result;
                    }
                }
            }
        }
        
        // Si encontramos un nombre de práctica, buscar su ID
        if ($practicaName !== null) {
            if (is_numeric($practicaName)) {
                $result['found'] = true;
                $result['id'] = (int)$practicaName;
                $result['is_valid'] = self::validateId($result['id']);
                if ($result['is_valid']) {
                    $practica = self::findOne($result['id']);
                    if ($practica) {
                        $result['name'] = $practica->nombre;
                    }
                }
            } else {
                $practicaId = self::findByName($practicaName);
                if ($practicaId !== null) {
                    $result['found'] = true;
                    $result['id'] = $practicaId;
                    $result['is_valid'] = true;
                    $practica = self::findOne($practicaId);
                    if ($practica) {
                        $result['name'] = $practica->nombre;
                    }
                }
            }
        }
        
        // Si aún no se encontró, buscar directamente en el texto de la consulta
        if (!$result['found'] && $userQuery !== null) {
            $practicaId = self::extractFromQuery($userQuery);
            if ($practicaId !== null) {
                $result['found'] = true;
                $result['id'] = $practicaId;
                $result['is_valid'] = true;
                $practica = self::findOne($practicaId);
                if ($practica) {
                    $result['name'] = $practica->nombre;
                }
            }
        }

        return $result;
    }
}
