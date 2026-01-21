<?php

namespace common\components;

use Yii;
use yii\helpers\Json;
use yii\helpers\Inflector;

/**
 * Gestor de templates JSON para form_config
 * Similar al sistema de vistas de Yii2
 * 
 * Estructura:
 * - frontend/views/json/common/_form.json (común a todos)
 * - frontend/views/json/{entity}/{action}.json (específico por método)
 */
class FormConfigTemplateManager
{
    /**
     * Directorio base de templates
     */
    const TEMPLATE_BASE_PATH = '@frontend/views/json';
    
    /**
     * Cargar y renderizar un template de form_config
     * 
     * @param string $entity Nombre de la entidad (ej: 'turnos')
     * @param string $action Nombre de la acción (ej: 'crear-mi-turno')
     * @param array $params Variables para procesar (ej: ['today' => '2024-01-01', 'idEfector' => 123])
     *                       También incluye los parámetros proporcionados por el usuario para calcular initial_step
     * @return array Configuración completa del wizard_config con initial_step calculado
     */
    public static function render($entity, $action, $params = [])
    {
        // 1. Cargar template común
        $commonConfig = self::loadCommonTemplate();
        Yii::info("Common config cargado: " . json_encode($commonConfig), 'form-config-template');
        
        // 2. Cargar template específico del método
        $specificConfig = self::loadSpecificTemplate($entity, $action);
        Yii::info("Specific config cargado para {$entity}/{$action}: " . json_encode($specificConfig), 'form-config-template');
        
        // 3. Merge: específico sobrescribe común, pero dentro de wizard_config
        $mergedConfig = self::mergeConfigs($commonConfig, $specificConfig);
        Yii::info("Merged config: " . json_encode($mergedConfig), 'form-config-template');
        
        // 4. Procesar variables dinámicas (como "today")
        $processedConfig = self::processVariables($mergedConfig, $params);
        
        // 5. Calcular initial_step basado en los parámetros proporcionados
        if (isset($processedConfig['wizard_config']['steps']) && isset($processedConfig['wizard_config']['fields'])) {
            $initialStep = self::calculateInitialStep(
                $processedConfig['wizard_config']['steps'],
                $processedConfig['wizard_config']['fields'],
                $params
            );
            $processedConfig['wizard_config']['initial_step'] = $initialStep;
        } else {
            Yii::warning("No se encontraron steps o fields en wizard_config después del merge. Steps: " . (isset($processedConfig['wizard_config']['steps']) ? 'existe' : 'no existe') . ", Fields: " . (isset($processedConfig['wizard_config']['fields']) ? 'existe' : 'no existe'), 'form-config-template');
        }
        
        return $processedConfig;
    }
    
    /**
     * Cargar template común
     */
    private static function loadCommonTemplate()
    {
        $commonPath = Yii::getAlias(self::TEMPLATE_BASE_PATH . '/common/_form.json');
        
        if (!file_exists($commonPath)) {
            return ['wizard_config' => []];
        }
        
        $content = file_get_contents($commonPath);
        $decoded = Json::decode($content);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::error("Error parseando JSON común: " . json_last_error_msg(), 'form-config-template');
            return ['wizard_config' => []];
        }
        
        return $decoded;
    }
    
    /**
     * Cargar template específico
     */
    private static function loadSpecificTemplate($entity, $action)
    {
        $templatePath = Yii::getAlias(
            self::TEMPLATE_BASE_PATH . '/' . strtolower($entity) . '/' . $action . '.json'
        );
        
        Yii::info("Buscando template específico en: {$templatePath}", 'form-config-template');
        
        if (!file_exists($templatePath)) {
            Yii::warning("Template específico no encontrado: {$templatePath}", 'form-config-template');
            return [];
        }
        
        $content = file_get_contents($templatePath);
        $decoded = Json::decode($content);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::error("Error parseando JSON específico en {$templatePath}: " . json_last_error_msg(), 'form-config-template');
            return [];
        }
        
        Yii::info("Template específico decodificado: " . json_encode($decoded), 'form-config-template');
        
        // Envolver en wizard_config si no está envuelto
        if (isset($decoded['steps']) || isset($decoded['fields'])) {
            Yii::info("Envolviendo template específico en wizard_config", 'form-config-template');
            return ['wizard_config' => $decoded];
        }
        
        Yii::info("Template específico ya tiene estructura wizard_config o diferente", 'form-config-template');
        return $decoded;
    }
    
    /**
     * Merge de configuraciones
     * El específico sobrescribe el común dentro de wizard_config
     */
    private static function mergeConfigs($common, $specific)
    {
        $result = $common;
        
        Yii::info("Iniciando merge. Common tiene wizard_config: " . (isset($result['wizard_config']) ? 'sí' : 'no'), 'form-config-template');
        Yii::info("Specific tiene wizard_config: " . (isset($specific['wizard_config']) ? 'sí' : 'no'), 'form-config-template');
        
        // Si el específico tiene wizard_config, merge dentro de él
        if (isset($specific['wizard_config'])) {
            if (!isset($result['wizard_config'])) {
                $result['wizard_config'] = [];
            }
            
            // Merge recursivo dentro de wizard_config
            foreach ($specific['wizard_config'] as $key => $value) {
                Yii::info("Mergeando key: {$key}, existe en common: " . (isset($result['wizard_config'][$key]) ? 'sí' : 'no'), 'form-config-template');
                
                if (isset($result['wizard_config'][$key]) && is_array($result['wizard_config'][$key]) && is_array($value)) {
                    // Para steps y fields, reemplazar completamente
                    if ($key === 'steps' || $key === 'fields') {
                        $result['wizard_config'][$key] = $value;
                        Yii::info("Reemplazado {$key} completamente con " . count($value) . " elementos", 'form-config-template');
                    } else {
                        // Para otros (navigation, validation, ui), merge recursivo
                        $result['wizard_config'][$key] = self::mergeArrays($result['wizard_config'][$key], $value);
                    }
                } else {
                    $result['wizard_config'][$key] = $value;
                    Yii::info("Agregado nuevo key: {$key}", 'form-config-template');
                }
            }
        } else {
            Yii::warning("Specific no tiene wizard_config, no se puede hacer merge", 'form-config-template');
        }
        
        Yii::info("Resultado del merge - tiene steps: " . (isset($result['wizard_config']['steps']) ? 'sí (' . count($result['wizard_config']['steps']) . ')' : 'no') . ", tiene fields: " . (isset($result['wizard_config']['fields']) ? 'sí (' . count($result['wizard_config']['fields']) . ')' : 'no'), 'form-config-template');
        
        return $result;
    }
    
    /**
     * Merge recursivo de arrays asociativos
     */
    private static function mergeArrays($base, $override)
    {
        foreach ($override as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = self::mergeArrays($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
    
    /**
     * Procesar variables dinámicas
     * Reemplaza valores especiales como "today" con valores reales
     * Procesa {{options}} en campos select para reemplazarlos con opciones reales
     */
    private static function processVariables($config, $params)
    {
        // Procesar "today" en campos date
        if (isset($config['wizard_config']['fields'])) {
            foreach ($config['wizard_config']['fields'] as &$field) {
                if (isset($field['min']) && $field['min'] === 'today') {
                    $field['min'] = $params['today'] ?? date('Y-m-d');
                }
                if (isset($field['max']) && $field['max'] === 'today') {
                    $field['max'] = $params['today'] ?? date('Y-m-d');
                }
                
                // Procesar {{options}} en campos select
                if (isset($field['type']) && $field['type'] === 'select') {
                    if (isset($field['options']) && $field['options'] === '{{options}}') {
                        $options = self::getOptionsForField($field, $params);
                        if ($options !== null) {
                            $field['options'] = $options;
                        } else {
                            // Si no se pueden obtener opciones, eliminar el campo options
                            unset($field['options']);
                        }
                    }
                }
            }
        }
        
        return $config;
    }
    
    /**
     * Obtener opciones para un campo select basándose en option_config
     * 
     * @param array $field Configuración del campo
     * @param array $params Parámetros disponibles (para dependencias)
     * @return array|null Array de opciones en formato [{'id': value, 'name': label}, ...] o null si no se pueden obtener
     */
    private static function getOptionsForField($field, $params)
    {
        if (!isset($field['option_config'])) {
            return null;
        }
        
        $optionConfig = $field['option_config'];
        $source = $optionConfig['source'] ?? null;
        $filter = $optionConfig['filter'] ?? null;
        $dependsOn = $field['depends_on'] ?? null;
        
        if (!$source) {
            return null;
        }
        
        // Si el campo depende de otro y no tenemos ese valor, no podemos obtener opciones
        if ($dependsOn && !isset($params[$dependsOn])) {
            return null;
        }
        
        $options = [];
        
        try {
            switch ($source) {
                case 'efectores':
                    $options = self::getEfectoresOptions($filter, $params);
                    break;
                case 'servicios':
                    $options = self::getServiciosOptions($filter, $params);
                    break;
                case 'rrhh':
                    $options = self::getRrhhOptions($filter, $params);
                    break;
                // Agregar más fuentes según sea necesario
                default:
                    Yii::warning("Fuente de opciones no soportada: {$source}", 'form-config-template');
                    return null;
            }
        } catch (\Exception $e) {
            Yii::error("Error obteniendo opciones para campo {$field['name']}: " . $e->getMessage(), 'form-config-template');
            return null;
        }
        
        return $options;
    }
    
    /**
     * Obtener opciones de efectores
     */
    private static function getEfectoresOptions($filter, $params)
    {
        $userId = Yii::$app->user->id ?? null;
        
        if ($filter === 'user_efectores' && $userId) {
            // Obtener efectores del usuario
            $efectores = \common\models\UserEfector::find()
                ->joinWith('idEfector')
                ->where(['user_efector.id_user' => $userId])
                ->andWhere('efectores.deleted_at IS NULL')
                ->orderBy('efectores.nombre')
                ->all();
            
            $options = [];
            foreach ($efectores as $efector) {
                $options[] = [
                    'id' => $efector->idEfector->id_efector,
                    'name' => $efector->idEfector->nombre,
                ];
            }
            return $options;
        } else {
            // Obtener todos los efectores
            $efectores = \common\models\Efector::find()
                ->where('deleted_at IS NULL')
                ->orderBy('nombre')
                ->all();
            
            $options = [];
            foreach ($efectores as $efector) {
                $options[] = [
                    'id' => $efector->id_efector,
                    'name' => $efector->nombre,
                ];
            }
            return $options;
        }
    }
    
    /**
     * Obtener opciones de servicios
     */
    private static function getServiciosOptions($filter, $params)
    {
        if ($filter === 'efector_servicios' && isset($params['id_efector'])) {
            // Obtener servicios del efector
            $servicios = \common\models\ServiciosEfector::find()
                ->joinWith('idServicio')
                ->where(['servicios_efector.id_efector' => $params['id_efector']])
                ->andWhere('servicios.deleted_at IS NULL')
                ->orderBy('servicios.nombre')
                ->all();
            
            $options = [];
            foreach ($servicios as $servicioEfector) {
                $options[] = [
                    'id' => $servicioEfector->idServicio->id_servicio,
                    'name' => $servicioEfector->idServicio->nombre,
                ];
            }
            return $options;
        } else {
            // Obtener todos los servicios
            $servicios = \common\models\Servicio::find()
                ->where('deleted_at IS NULL')
                ->orderBy('nombre')
                ->all();
            
            $options = [];
            foreach ($servicios as $servicio) {
                $options[] = [
                    'id' => $servicio->id_servicio,
                    'name' => $servicio->nombre,
                ];
            }
            return $options;
        }
    }
    
    /**
     * Obtener opciones de RRHH
     */
    private static function getRrhhOptions($filter, $params)
    {
        // Para RRHH, generalmente necesitamos filtros adicionales
        // Por ahora, retornamos opciones vacías ya que RRHH generalmente requiere autocomplete
        // Si se necesita, se puede implementar similar a los otros
        return [];
    }
    
    /**
     * Calcular el paso inicial del wizard basándose en los pasos y parámetros proporcionados
     * 
     * @param array $wizardSteps Array de pasos del wizard (con fields como nombres o objetos)
     * @param array $fieldsConfig Configuración de todos los campos (para verificar required)
     * @param array $providedParams Parámetros ya proporcionados por el usuario
     * @return int Índice del paso inicial (0-based)
     */
    private static function calculateInitialStep($wizardSteps, $fieldsConfig, $providedParams)
    {
        if (empty($wizardSteps)) {
            return 0;
        }
        
        // Si no hay parámetros proporcionados, empezar desde el primer paso
        if (empty($providedParams)) {
            return 0;
        }
        
        // Crear un mapa de configuración de campos por nombre para acceso rápido
        $fieldsMap = [];
        foreach ($fieldsConfig as $field) {
            $fieldName = $field['name'] ?? null;
            if (!empty($fieldName)) {
                $fieldsMap[$fieldName] = $field;
            }
        }
        
        // Verificar cada paso en orden para encontrar el primero con campos incompletos
        foreach ($wizardSteps as $stepIndex => $step) {
            $stepFields = $step['fields'] ?? [];
            $stepComplete = true;
            
            // Verificar si todos los campos requeridos de este paso tienen valores
            foreach ($stepFields as $field) {
                // El campo puede ser un string (nombre) o un array con 'name'
                $fieldName = is_array($field) ? ($field['name'] ?? null) : $field;
                
                if (empty($fieldName)) {
                    continue;
                }
                
                // Obtener configuración del campo para verificar si es requerido
                $fieldConfig = $fieldsMap[$fieldName] ?? null;
                
                // Si el campo es requerido y no tiene valor, el paso no está completo
                $isRequired = $fieldConfig['required'] ?? false;
                $hasValue = isset($providedParams[$fieldName]) && 
                           $providedParams[$fieldName] !== null && 
                           $providedParams[$fieldName] !== '';
                
                if ($isRequired && !$hasValue) {
                    $stepComplete = false;
                    break;
                }
            }
            
            // Si este paso no está completo, este es el paso inicial
            if (!$stepComplete) {
                return $stepIndex;
            }
        }
        
        // Si todos los pasos están completos, mostrar el último paso (confirmación)
        return count($wizardSteps) - 1;
    }
}
