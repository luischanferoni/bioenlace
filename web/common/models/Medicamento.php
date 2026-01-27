<?php

/**
* @autor: Ivana Beltrán y María de los A. Valdez
* @versión: 1.1 
* @creacion: 25/02/2016
* @modificacion: 
**/

namespace common\models;

use Yii;
use common\traits\ParameterQuestionsTrait;

/**
 * This is the model class for table "medicamentos".
 *
 * @property integer $id_medicamento
 * @property string $generico
 * @property string $presentacion
 *
 * @property MedicamentosConsultas[] $medicamentosConsultas
 * @property Consultas[] $idConsultas
 */
class Medicamento extends \yii\db\ActiveRecord
{
    use ParameterQuestionsTrait;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'medicamentos';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['generico', 'presentacion'], 'string', 'max' => 60]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_medicamento' => 'Id Medicamento',
            'generico' => 'Generico',
            'presentacion' => 'Presentacion',
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'medicamento' => '¿Qué medicamento querés consultar?',
            'id_medicamento' => '¿Qué medicamento querés consultar?',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMedicamentosConsultas()
    {
        return $this->hasMany(MedicamentosConsultas::className(), ['id_medicamento' => 'id_medicamento']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdConsultas()
    {
        return $this->hasMany(Consultas::className(), ['id_consulta' => 'id_consulta'])->viaTable('medicamentos_consultas', ['id_medicamento' => 'id_medicamento']);
    }
    
    public function getMedicamentoConcat() {
        return $this->generico.' - '.$this->presentacion;
    }

    /**
     * Validar si un id_medicamento existe en la base de datos
     * @param int $idMedicamento
     * @return bool
     */
    public static function validateId($idMedicamento)
    {
        try {
            $medicamento = self::findOne($idMedicamento);
            return $medicamento !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando id_medicamento {$idMedicamento}: " . $e->getMessage(), 'medicamento-model');
            return false;
        }
    }

    /**
     * Buscar medicamento por nombre (generico o presentacion)
     * 
     * @param string $nombre Nombre del medicamento
     * @return int|null ID del medicamento encontrado
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        
        $nombreNormalizado = trim($nombre);
        
        try {
            // Buscar por genérico o presentación
            $medicamento = self::find()
                ->where(['or',
                    ['like', 'generico', $nombreNormalizado],
                    ['like', 'presentacion', $nombreNormalizado]
                ])
                ->one();
            
            if ($medicamento) {
                return (int)$medicamento->id_medicamento;
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando medicamento por nombre '{$nombre}': " . $e->getMessage(), 'medicamento-model');
        }
        
        return null;
    }

    /**
     * Extraer medicamento desde el texto de la consulta del usuario
     * 
     * @param string $userQuery Texto de la consulta del usuario
     * @return int|null ID del medicamento encontrado
     */
    public static function extractFromQuery($userQuery)
    {
        // Por ahora retornamos null, la búsqueda se hace principalmente por nombre completo
        return null;
    }

    /**
     * Buscar y validar medicamento desde datos extraídos y userQuery
     * 
     * @param array $extractedData Datos extraídos por la IA
     * @param string|null $userQuery Texto original de la consulta (opcional)
     * @param string|null $paramName Nombre del parámetro específico a buscar (ej: 'id_medicamento', 'medicamento')
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

        // Buscar id_medicamento directamente en extracted_data
        if (isset($extractedData['id_medicamento'])) {
            $idMedicamento = $extractedData['id_medicamento'];
            if (is_numeric($idMedicamento)) {
                $result['found'] = true;
                $result['id'] = (int)$idMedicamento;
                $result['is_valid'] = self::validateId($result['id']);
                if ($result['is_valid']) {
                    $medicamento = self::findOne($result['id']);
                    if ($medicamento) {
                        $result['name'] = $medicamento->getMedicamentoConcat();
                    }
                }
                return $result;
            }
        }
        
        // Buscar medicamento por nombre en extracted_data
        $medicamentoName = null;
        $searchKeys = ['medicamento'];
        if ($paramName) {
            array_unshift($searchKeys, $paramName);
        }
        
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key])) {
                $medicamentoName = $extractedData[$key];
                break;
            }
        }
        
        // Buscar en raw data
        if ($medicamentoName === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['medicamento'])) {
                $medicamentoName = $extractedData['raw']['medicamento'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser medicamentos
                foreach ($extractedData['raw']['names'] as $name) {
                    $medicamentoId = self::findByName($name);
                    if ($medicamentoId !== null) {
                        $result['found'] = true;
                        $result['id'] = $medicamentoId;
                        $result['is_valid'] = true;
                        $medicamento = self::findOne($medicamentoId);
                        if ($medicamento) {
                            $result['name'] = $medicamento->getMedicamentoConcat();
                        }
                        return $result;
                    }
                }
            }
        }
        
        // Si encontramos un nombre de medicamento, buscar su ID
        if ($medicamentoName !== null) {
            if (is_numeric($medicamentoName)) {
                $result['found'] = true;
                $result['id'] = (int)$medicamentoName;
                $result['is_valid'] = self::validateId($result['id']);
                if ($result['is_valid']) {
                    $medicamento = self::findOne($result['id']);
                    if ($medicamento) {
                        $result['name'] = $medicamento->getMedicamentoConcat();
                    }
                }
            } else {
                $medicamentoId = self::findByName($medicamentoName);
                if ($medicamentoId !== null) {
                    $result['found'] = true;
                    $result['id'] = $medicamentoId;
                    $result['is_valid'] = true;
                    $medicamento = self::findOne($medicamentoId);
                    if ($medicamento) {
                        $result['name'] = $medicamento->getMedicamentoConcat();
                    }
                }
            }
        }
        
        // Si aún no se encontró, buscar directamente en el texto de la consulta
        if (!$result['found'] && $userQuery !== null) {
            $medicamentoId = self::extractFromQuery($userQuery);
            if ($medicamentoId !== null) {
                $result['found'] = true;
                $result['id'] = $medicamentoId;
                $result['is_valid'] = true;
                $medicamento = self::findOne($medicamentoId);
                if ($medicamento) {
                    $result['name'] = $medicamento->getMedicamentoConcat();
                }
            }
        }

        return $result;
    }
}
