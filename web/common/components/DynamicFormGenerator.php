<?php

namespace common\components;

use Yii;
use yii\db\ActiveRecord;
use ReflectionClass;

/**
 * Generador de formularios dinámicos basados en modelos ActiveRecord
 * Genera estructuras JSON para formularios interactivos con componentes especializados
 */
class DynamicFormGenerator
{
    /**
     * Generar estructura de formulario para un modelo
     * @param string $modelClass Clase del modelo
     * @param string $operation Operación: 'create' o 'update'
     * @param array $prefilledValues Valores prellenados desde la consulta del usuario
     * @return array Estructura del formulario
     */
    public static function generateForm($modelClass, $operation = 'create', $prefilledValues = [])
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, 'yii\db\ActiveRecord')) {
            return [
                'success' => false,
                'error' => 'Modelo no válido',
            ];
        }

        try {
            $model = new $modelClass();
            $schema = $model::getTableSchema();
            $rules = $model->rules();
            $labels = $model->attributeLabels();

            $fields = [];
            $requiredFields = [];

            // Analizar reglas para identificar campos requeridos
            foreach ($rules as $rule) {
                if (is_array($rule) && isset($rule[0]) && isset($rule[1])) {
                    $attributes = is_array($rule[0]) ? $rule[0] : [$rule[0]];
                    $validator = $rule[1];

                    if ($validator === 'required') {
                        foreach ($attributes as $attr) {
                            $requiredFields[] = $attr;
                        }
                    }
                }
            }

            // Generar campos desde el esquema de la tabla
            foreach ($schema->columns as $column) {
                $fieldName = $column->name;
                
                // Saltar campos de auditoría y timestamps comunes
                if (in_array($fieldName, ['created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'])) {
                    continue;
                }

                $field = self::generateField($column, $model, $labels, $requiredFields, $prefilledValues);
                
                if ($field) {
                    $fields[] = $field;
                }
            }

            return [
                'success' => true,
                'model_class' => $modelClass,
                'model_name' => (new ReflectionClass($modelClass))->getShortName(),
                'operation' => $operation,
                'fields' => $fields,
                'form_action' => self::getFormAction($modelClass, $operation),
            ];

        } catch (\Exception $e) {
            Yii::error("Error generando formulario: " . $e->getMessage(), 'dynamic-form');
            return [
                'success' => false,
                'error' => 'Error al generar formulario',
            ];
        }
    }

    /**
     * Generar campo individual
     * @param \yii\db\ColumnSchema $column
     * @param ActiveRecord $model
     * @param array $labels
     * @param array $requiredFields
     * @param array $prefilledValues
     * @return array|null
     */
    private static function generateField($column, $model, $labels, $requiredFields, $prefilledValues)
    {
        $fieldName = $column->name;
        $label = isset($labels[$fieldName]) ? $labels[$fieldName] : ucfirst(str_replace('_', ' ', $fieldName));
        $isRequired = in_array($fieldName, $requiredFields);
        $prefilledValue = isset($prefilledValues[$fieldName]) ? $prefilledValues[$fieldName] : null;

        // Determinar tipo de campo según el tipo de columna y reglas
        $fieldType = self::determineFieldType($column, $model, $fieldName);
        
        $field = [
            'name' => $fieldName,
            'label' => $label,
            'type' => $fieldType['type'],
            'required' => $isRequired,
            'value' => $prefilledValue !== null ? $prefilledValue : ($column->defaultValue ?? null),
        ];

        // Agregar opciones según el tipo
        switch ($fieldType['type']) {
            case 'select':
            case 'radio':
                $field['options'] = $fieldType['options'];
                break;

            case 'number':
                // Agregar opciones rápidas y control +/- para campos numéricos
                $field['quick_options'] = [5, 15, 30]; // Opciones rápidas por defecto
                $field['min'] = $fieldType['min'] ?? null;
                $field['max'] = $fieldType['max'] ?? null;
                $field['step'] = $fieldType['step'] ?? 1;
                break;

            case 'date':
                // Procesar valores como "mañana", "hoy", etc.
                if ($prefilledValue && is_string($prefilledValue)) {
                    $field['value'] = self::processDateValue($prefilledValue);
                }
                break;
        }

        // Agregar validaciones
        $field['validations'] = self::extractValidations($model, $fieldName);

        return $field;
    }

    /**
     * Determinar tipo de campo según columna y modelo
     * @param \yii\db\ColumnSchema $column
     * @param ActiveRecord $model
     * @param string $fieldName
     * @return array
     */
    private static function determineFieldType($column, $model, $fieldName)
    {
        $phpType = $column->phpType;
        $dbType = $column->type;

        // Verificar si es una relación (foreign key)
        if (self::isForeignKey($model, $fieldName)) {
            $options = self::getRelationOptions($model, $fieldName);
            if (!empty($options)) {
                return [
                    'type' => 'select',
                    'options' => $options,
                ];
            }
        }

        // Verificar si tiene opciones predefinidas (enums, constantes)
        $enumOptions = self::getEnumOptions($model, $fieldName);
        if ($enumOptions) {
            return [
                'type' => 'radio', // Usar radio buttons para opciones
                'options' => $enumOptions,
            ];
        }

        // Determinar tipo según tipo de dato
        if (stripos($dbType, 'int') !== false || $phpType === 'integer') {
            return [
                'type' => 'number',
                'min' => null,
                'max' => null,
                'step' => 1,
            ];
        }

        if (stripos($dbType, 'decimal') !== false || stripos($dbType, 'float') !== false || $phpType === 'double') {
            return [
                'type' => 'number',
                'min' => null,
                'max' => null,
                'step' => 0.01,
            ];
        }

        if (stripos($dbType, 'date') !== false || stripos($dbType, 'time') !== false) {
            return ['type' => 'date'];
        }

        if (stripos($dbType, 'text') !== false || $column->size > 255) {
            return ['type' => 'textarea'];
        }

        // Por defecto, texto
        return ['type' => 'text'];
    }

    /**
     * Verificar si un campo es foreign key
     * @param ActiveRecord $model
     * @param string $fieldName
     * @return bool
     */
    private static function isForeignKey($model, $fieldName)
    {
        $schema = $model::getTableSchema();
        $foreignKeys = $schema->foreignKeys;
        
        foreach ($foreignKeys as $fk) {
            if (isset($fk[$fieldName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener opciones de relación
     * @param ActiveRecord $model
     * @param string $fieldName
     * @return array
     */
    private static function getRelationOptions($model, $fieldName)
    {
        // Intentar obtener opciones desde la relación
        // Esto es una simplificación - en producción se debería analizar la relación real
        try {
            $modelClass = get_class($model);
            $schema = $model::getTableSchema();
            $foreignKeys = $schema->foreignKeys;
            
            foreach ($foreignKeys as $fk) {
                if (isset($fk[$fieldName])) {
                    $relatedTable = array_keys($fk)[0];
                    $relatedClass = self::findModelClassByTable($relatedTable);
                    
                    if ($relatedClass) {
                        // Obtener opciones desde el modelo relacionado
                        $relatedModel = new $relatedClass();
                        if (method_exists($relatedModel, 'getLista')) {
                            return $relatedModel->getLista();
                        }
                        
                        // Intentar obtener por nombre común
                        $nameField = self::guessNameField($relatedModel);
                        if ($nameField) {
                            $items = $relatedClass::find()->limit(100)->all();
                            $options = [];
                            foreach ($items as $item) {
                                $pk = is_array($item->primaryKey) ? reset($item->primaryKey) : $item->primaryKey;
                                $options[] = [
                                    'value' => $pk,
                                    'label' => $item->$nameField,
                                ];
                            }
                            return $options;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignorar errores
        }

        return [];
    }

    /**
     * Obtener opciones de enum/constantes
     * @param ActiveRecord $model
     * @param string $fieldName
     * @return array|null
     */
    private static function getEnumOptions($model, $fieldName)
    {
        $reflection = new ReflectionClass($model);
        $constants = $reflection->getConstants();
        
        // Buscar constantes relacionadas con el campo
        $prefix = strtoupper($fieldName) . '_';
        $options = [];
        
        foreach ($constants as $name => $value) {
            if (strpos($name, $prefix) === 0) {
                $label = str_replace('_', ' ', substr($name, strlen($prefix)));
                $options[] = [
                    'value' => $value,
                    'label' => ucwords(strtolower($label)),
                ];
            }
        }

        // Buscar arrays de constantes (ej: ESTADOS = [...])
        $relatedConstants = [
            strtoupper($fieldName) . 'S',
            strtoupper($fieldName) . '_OPTIONS',
            'ESTADOS',
            'TIPOS',
        ];

        foreach ($relatedConstants as $constName) {
            if (isset($constants[$constName]) && is_array($constants[$constName])) {
                foreach ($constants[$constName] as $key => $value) {
                    $options[] = [
                        'value' => $key,
                        'label' => is_array($value) ? ($value['label'] ?? $value) : $value,
                    ];
                }
                if (!empty($options)) {
                    return $options;
                }
            }
        }

        return !empty($options) ? $options : null;
    }

    /**
     * Extraer validaciones de un campo
     * @param ActiveRecord $model
     * @param string $fieldName
     * @return array
     */
    private static function extractValidations($model, $fieldName)
    {
        $validations = [];
        $rules = $model->rules();

        foreach ($rules as $rule) {
            if (is_array($rule) && isset($rule[0]) && isset($rule[1])) {
                $attributes = is_array($rule[0]) ? $rule[0] : [$rule[0]];
                
                if (in_array($fieldName, $attributes)) {
                    $validator = $rule[1];
                    $validation = ['type' => $validator];

                    // Extraer parámetros adicionales
                    if (isset($rule['min'])) {
                        $validation['min'] = $rule['min'];
                    }
                    if (isset($rule['max'])) {
                        $validation['max'] = $rule['max'];
                    }
                    if (isset($rule['length'])) {
                        $validation['length'] = $rule['length'];
                    }

                    $validations[] = $validation;
                }
            }
        }

        return $validations;
    }

    /**
     * Procesar valores de fecha como "mañana", "hoy", etc.
     * @param string $value
     * @return string|null
     */
    private static function processDateValue($value)
    {
        $value = strtolower(trim($value));
        
        switch ($value) {
            case 'mañana':
            case 'manana':
            case 'tomorrow':
                return date('Y-m-d', strtotime('+1 day'));
            case 'hoy':
            case 'today':
                return date('Y-m-d');
            case 'ayer':
            case 'yesterday':
                return date('Y-m-d', strtotime('-1 day'));
            default:
                // Intentar parsear como fecha
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    return date('Y-m-d', $timestamp);
                }
        }

        return null;
    }

    /**
     * Obtener acción del formulario
     * @param string $modelClass
     * @param string $operation
     * @return string
     */
    private static function getFormAction($modelClass, $operation)
    {
        $reflection = new ReflectionClass($modelClass);
        $modelName = $reflection->getShortName();
        
        // Convertir a nombre de controlador
        $controllerName = strtolower($modelName);
        
        $path = Yii::$app->params['path'] ?? '';
        $path = trim($path, '/');
        
        return '/' . $path . '/' . $controllerName . '/' . $operation;
    }

    /**
     * Buscar clase de modelo por nombre de tabla
     * @param string $tableName
     * @return string|null
     */
    private static function findModelClassByTable($tableName)
    {
        // Simplificación - en producción se debería buscar en todos los modelos
        $models = ModelDiscoveryService::discoverAllModels(false);
        
        foreach ($models as $model) {
            if ($model['table_name'] === $tableName) {
                return $model['class'];
            }
        }

        return null;
    }

    /**
     * Adivinar campo de nombre en un modelo
     * @param ActiveRecord $model
     * @return string|null
     */
    private static function guessNameField($model)
    {
        $commonNames = ['nombre', 'name', 'descripcion', 'description', 'titulo', 'title'];
        
        foreach ($commonNames as $name) {
            if ($model->hasAttribute($name)) {
                return $name;
            }
        }

        return null;
    }
}

