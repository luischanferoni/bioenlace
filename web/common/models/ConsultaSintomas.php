<?php

namespace common\models;

use common\models\snomed\SnomedProblemas;
use Yii;
use common\traits\ParameterQuestionsTrait;

/**
 * This is the model class for table "consultas_sintomas".
 *
 * @property string $id_consulta
 * @property string $codigo
 * @property string $tipo_sintomas
 *
 * @property Cie10 $codigo0
 * @property Consultas $idConsulta
 */
class ConsultaSintomas extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    use ParameterQuestionsTrait;

    public $select2_codigo;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consultas_sintomas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_consulta', 'select2_codigo', 'codigo'], 'required'],
            [['id_consulta'], 'integer'],
            [['codigo'], 'string'],
            ['select2_codigo', 'each', 'rule' => ['string']],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_consulta' => 'Consulta',
            'codigo' => 'Sintoma',
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'sintoma' => '¿Qué síntoma tenés?',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodigoSnomed()
    {
        return $this->hasOne(SnomedProblemas::className(), ['conceptId' => 'codigo']);
    }

    /**
     * Mientras la consulta no este finalizada (nueva o editando) el usuario
     * puede hacer un hard delete
     */
    public static function hardDeleteGrupo($id_consulta, $ids)
    {
        if (count($ids) > 0 && isset($id_consulta) && $id_consulta != "" && $id_consulta != 0) {
            self::hardDeleteAll([
                'AND',
                ['in', 'id', $ids],
                ['=', 'id_consulta', $id_consulta]
            ]);
        }
    }

    /**
     * Validar si un código de síntoma existe (usando SNOMED)
     * @param string $codigo Código SNOMED del síntoma
     * @return bool
     */
    public static function validateId($codigo)
    {
        try {
            // ConsultaSintomas usa códigos SNOMED, no IDs numéricos
            // Validar que el código existe en SnomedProblemas
            $snomed = \common\models\snomed\SnomedProblemas::findOne(['conceptId' => $codigo]);
            return $snomed !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando código síntoma {$codigo}: " . $e->getMessage(), 'consulta-sintomas-model');
            return false;
        }
    }

    /**
     * Buscar síntoma por nombre (busca en SNOMED)
     * 
     * @param string $nombre Nombre del síntoma
     * @return string|null Código SNOMED del síntoma encontrado
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        
        $nombreNormalizado = trim($nombre);
        
        try {
            // Buscar en SnomedProblemas por término preferido o sinónimo
            $snomed = \common\models\snomed\SnomedProblemas::find()
                ->where(['or',
                    ['like', 'term', $nombreNormalizado],
                    ['like', 'fsn', $nombreNormalizado]
                ])
                ->one();
            
            if ($snomed) {
                return $snomed->conceptId;
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando síntoma por nombre '{$nombre}': " . $e->getMessage(), 'consulta-sintomas-model');
        }
        
        return null;
    }

    /**
     * Extraer síntoma desde el texto de la consulta del usuario
     * 
     * @param string $userQuery Texto de la consulta del usuario
     * @return string|null Código SNOMED del síntoma encontrado
     */
    public static function extractFromQuery($userQuery)
    {
        // Por ahora retornamos null, la búsqueda se hace principalmente por nombre completo
        return null;
    }

    /**
     * Buscar y validar síntoma desde datos extraídos y userQuery
     * 
     * @param array $extractedData Datos extraídos por la IA
     * @param string|null $userQuery Texto original de la consulta (opcional)
     * @param string|null $paramName Nombre del parámetro específico a buscar (ej: 'sintoma', 'sintomas')
     * @return array ['found' => bool, 'id' => string|null, 'name' => string|null, 'is_valid' => bool]
     */
    public static function findAndValidate($extractedData, $userQuery = null, $paramName = null)
    {
        $result = [
            'found' => false,
            'id' => null,
            'name' => null,
            'is_valid' => false,
        ];

        // Buscar código de síntoma directamente en extracted_data
        $searchKeys = ['sintoma', 'sintomas', 'codigo'];
        if ($paramName) {
            array_unshift($searchKeys, $paramName);
        }
        
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key])) {
                $codigo = $extractedData[$key];
                if (is_string($codigo) && !empty($codigo)) {
                    $result['found'] = true;
                    $result['id'] = $codigo;
                    $result['is_valid'] = self::validateId($codigo);
                    if ($result['is_valid']) {
                        $snomed = \common\models\snomed\SnomedProblemas::findOne(['conceptId' => $codigo]);
                        if ($snomed) {
                            $result['name'] = $snomed->term;
                        }
                    }
                    return $result;
                }
            }
        }
        
        // Buscar síntoma por nombre en extracted_data
        $sintomaName = null;
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key]) && is_string($extractedData[$key])) {
                $sintomaName = $extractedData[$key];
                break;
            }
        }
        
        // Buscar en raw data
        if ($sintomaName === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['sintoma'])) {
                $sintomaName = $extractedData['raw']['sintoma'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser síntomas
                foreach ($extractedData['raw']['names'] as $name) {
                    $codigo = self::findByName($name);
                    if ($codigo !== null) {
                        $result['found'] = true;
                        $result['id'] = $codigo;
                        $result['is_valid'] = true;
                        $snomed = \common\models\snomed\SnomedProblemas::findOne(['conceptId' => $codigo]);
                        if ($snomed) {
                            $result['name'] = $snomed->term;
                        }
                        return $result;
                    }
                }
            }
        }
        
        // Si encontramos un nombre de síntoma, buscar su código
        if ($sintomaName !== null && is_string($sintomaName)) {
            $codigo = self::findByName($sintomaName);
            if ($codigo !== null) {
                $result['found'] = true;
                $result['id'] = $codigo;
                $result['is_valid'] = true;
                $snomed = \common\models\snomed\SnomedProblemas::findOne(['conceptId' => $codigo]);
                if ($snomed) {
                    $result['name'] = $snomed->term;
                }
            }
        }
        
        // Si aún no se encontró, buscar directamente en el texto de la consulta
        if (!$result['found'] && $userQuery !== null) {
            $codigo = self::extractFromQuery($userQuery);
            if ($codigo !== null) {
                $result['found'] = true;
                $result['id'] = $codigo;
                $result['is_valid'] = true;
                $snomed = \common\models\snomed\SnomedProblemas::findOne(['conceptId' => $codigo]);
                if ($snomed) {
                    $result['name'] = $snomed->term;
                }
            }
        }

        return $result;
    }
}
