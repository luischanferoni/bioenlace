<?php

namespace common\components;

use Yii;
use yii\helpers\Json;
use yii\helpers\Inflector;

/**
 * Gestor de plantillas JSON que describen **definiciones de UI** (wizards, listas, detalles, etc.).
 * No está limitado a formularios: el descriptor puede evolucionar según `ui_type` / `kind` en la respuesta.
 *
 * Estructura de archivos (bajo el módulo API v1):
 * - frontend/modules/api/v1/views/json/common/_form.json (fragmentos comunes dentro de wizard_config)
 * - frontend/modules/api/v1/views/json/{entidad}/{accion}.json (descriptor por flujo)
 *
 * Metadatos opcionales de compatibilidad por cliente (en el JSON del flujo, típicamente junto a steps/fields):
 *
 * ```json
 * "ui_meta": {
 *   "schema_version": "1",
 *   "clients": {
 *     "*": { "min_app_version": "1.0.0" },
 *     "paciente-flutter": { "min_app_version": "2.0.0", "max_app_version": "99.0.0" }
 *   }
 * }
 * ```
 *
 * Headers HTTP que el cliente puede enviar (opcionales): X-App-Client, X-App-Version.
 */
class UiDefinitionTemplateManager
{
    public const TEMPLATE_BASE_PATH = '@frontend/modules/api/v1/views/json';

    public const LOG_CATEGORY = 'ui-definition-template';

    /**
     * Cargar y combinar plantillas y devolver un array con `wizard_config` (u otras claves mergeadas).
     *
     * @param string $entity ej. 'turnos'
     * @param string $action ej. 'crear-mi-turno'
     * @param array $params variables (today, query params, etc.)
     * @return array
     */
    public static function render($entity, $action, $params = [])
    {
        $commonConfig = self::loadCommonTemplate();
        Yii::info('Common config cargado: ' . json_encode($commonConfig), self::LOG_CATEGORY);

        $specificConfig = self::loadSpecificTemplate($entity, $action);
        Yii::info("Specific config cargado para {$entity}/{$action}: " . json_encode($specificConfig), self::LOG_CATEGORY);

        $mergedConfig = self::mergeConfigs($commonConfig, $specificConfig);
        Yii::info('Merged config: ' . json_encode($mergedConfig), self::LOG_CATEGORY);

        $processedConfig = self::processVariables($mergedConfig, $params);

        if (isset($processedConfig['wizard_config']['steps']) && isset($processedConfig['wizard_config']['fields'])) {
            $initialStep = self::calculateInitialStep(
                $processedConfig['wizard_config']['steps'],
                $processedConfig['wizard_config']['fields'],
                $params
            );
            $processedConfig['wizard_config']['initial_step'] = $initialStep;
        } else {
            Yii::warning(
                'No se encontraron steps o fields en wizard_config después del merge. Steps: '
                . (isset($processedConfig['wizard_config']['steps']) ? 'existe' : 'no existe')
                . ', Fields: '
                . (isset($processedConfig['wizard_config']['fields']) ? 'existe' : 'no existe'),
                self::LOG_CATEGORY
            );
        }

        return $processedConfig;
    }

    /**
     * Evalúa si la versión de app declarada cumple `ui_meta.clients` dentro de wizard_config.
     *
     * @param array $wizardConfig fragmento wizard_config (puede contener ui_meta)
     * @param string|null $clientId valor de X-App-Client (ej. paciente-flutter)
     * @param string|null $appVersion valor de X-App-Version (semver simple)
     * @return array{compatible: bool, client_id: ?string, client_version: ?string, applied_rule: ?string, requirements: array, warnings: string[]}
     */
    public static function evaluateClientCompatibility(array $wizardConfig, $clientId, $appVersion)
    {
        $warnings = [];
        $uiMeta = $wizardConfig['ui_meta'] ?? [];
        $clients = $uiMeta['clients'] ?? null;

        if (empty($clients) || !is_array($clients)) {
            if ($appVersion === null || $appVersion === '') {
                $warnings[] = 'No se envió X-App-Version; no se puede comprobar compatibilidad con precisión.';
            }
            return [
                'compatible' => true,
                'client_id' => $clientId,
                'client_version' => $appVersion,
                'applied_rule' => null,
                'requirements' => [],
                'warnings' => $warnings,
            ];
        }

        if ($appVersion === null || $appVersion === '') {
            $warnings[] = 'No se envió X-App-Version; se asume compatible pero conviene enviar la versión.';
        }

        $ruleKey = null;
        $rule = null;
        $clientNorm = $clientId !== null && $clientId !== '' ? strtolower(trim($clientId)) : null;

        if ($clientNorm && isset($clients[$clientNorm]) && is_array($clients[$clientNorm])) {
            $ruleKey = $clientNorm;
            $rule = $clients[$clientNorm];
        } elseif (isset($clients['*']) && is_array($clients['*'])) {
            $ruleKey = '*';
            $rule = $clients['*'];
        } elseif (isset($clients['default']) && is_array($clients['default'])) {
            $ruleKey = 'default';
            $rule = $clients['default'];
        }

        if ($rule === null) {
            return [
                'compatible' => true,
                'client_id' => $clientId,
                'client_version' => $appVersion,
                'applied_rule' => null,
                'requirements' => $clients,
                'warnings' => array_merge($warnings, ['No hay regla de compatibilidad para este cliente; se omite el chequeo estricto.']),
            ];
        }

        $min = isset($rule['min_app_version']) ? (string) $rule['min_app_version'] : null;
        $max = isset($rule['max_app_version']) ? (string) $rule['max_app_version'] : null;

        $compatible = true;
        if ($appVersion !== null && $appVersion !== '') {
            if ($min !== null && $min !== '' && version_compare($appVersion, $min, '<')) {
                $compatible = false;
            }
            if ($compatible && $max !== null && $max !== '' && version_compare($appVersion, $max, '>')) {
                $compatible = false;
            }
        }

        return [
            'compatible' => $compatible,
            'client_id' => $clientId,
            'client_version' => $appVersion,
            'applied_rule' => $ruleKey,
            'requirements' => [
                'min_app_version' => $min,
                'max_app_version' => $max,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Extrae headers estándar de cliente para compatibilidad / logging.
     *
     * @return array{client: ?string, version: ?string, android_sdk: ?string}
     */
    public static function getClientHeadersFromRequest()
    {
        $request = Yii::$app->request;
        return [
            'client' => $request->headers->get('X-App-Client') ?: null,
            'version' => $request->headers->get('X-App-Version') ?: null,
            'android_sdk' => $request->headers->get('X-Android-Sdk') ?: null,
        ];
    }

    private static function loadCommonTemplate()
    {
        $commonPath = Yii::getAlias(self::TEMPLATE_BASE_PATH . '/common/_form.json');

        if (!file_exists($commonPath)) {
            return ['wizard_config' => []];
        }

        $content = file_get_contents($commonPath);
        $decoded = Json::decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::error('Error parseando JSON común: ' . json_last_error_msg(), self::LOG_CATEGORY);
            return ['wizard_config' => []];
        }

        return $decoded;
    }

    private static function loadSpecificTemplate($entity, $action)
    {
        $templatePath = Yii::getAlias(
            self::TEMPLATE_BASE_PATH . '/' . strtolower($entity) . '/' . $action . '.json'
        );

        Yii::info("Buscando template específico en: {$templatePath}", self::LOG_CATEGORY);

        if (!file_exists($templatePath)) {
            Yii::warning("Template específico no encontrado: {$templatePath}", self::LOG_CATEGORY);
            return [];
        }

        $content = file_get_contents($templatePath);
        $decoded = Json::decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::error("Error parseando JSON específico en {$templatePath}: " . json_last_error_msg(), self::LOG_CATEGORY);
            return [];
        }

        Yii::info('Template específico decodificado: ' . json_encode($decoded), self::LOG_CATEGORY);

        if (isset($decoded['steps']) || isset($decoded['fields'])) {
            Yii::info('Envolviendo template específico en wizard_config', self::LOG_CATEGORY);
            return ['wizard_config' => $decoded];
        }

        Yii::info('Template específico ya tiene estructura wizard_config o diferente', self::LOG_CATEGORY);
        return $decoded;
    }

    private static function mergeConfigs($common, $specific)
    {
        $result = $common;

        Yii::info('Iniciando merge. Common tiene wizard_config: ' . (isset($result['wizard_config']) ? 'sí' : 'no'), self::LOG_CATEGORY);
        Yii::info('Specific tiene wizard_config: ' . (isset($specific['wizard_config']) ? 'sí' : 'no'), self::LOG_CATEGORY);

        if (isset($specific['wizard_config'])) {
            if (!isset($result['wizard_config'])) {
                $result['wizard_config'] = [];
            }

            foreach ($specific['wizard_config'] as $key => $value) {
                Yii::info("Mergeando key: {$key}, existe en common: " . (isset($result['wizard_config'][$key]) ? 'sí' : 'no'), self::LOG_CATEGORY);

                if (isset($result['wizard_config'][$key]) && is_array($result['wizard_config'][$key]) && is_array($value)) {
                    if ($key === 'steps' || $key === 'fields') {
                        $result['wizard_config'][$key] = $value;
                        Yii::info("Reemplazado {$key} completamente con " . count($value) . ' elementos', self::LOG_CATEGORY);
                    } elseif ($key === 'ui_meta') {
                        $result['wizard_config'][$key] = self::mergeUiMeta($result['wizard_config'][$key], $value);
                    } else {
                        $result['wizard_config'][$key] = self::mergeArrays($result['wizard_config'][$key], $value);
                    }
                } else {
                    $result['wizard_config'][$key] = $value;
                    Yii::info("Agregado nuevo key: {$key}", self::LOG_CATEGORY);
                }
            }
        } else {
            Yii::warning('Specific no tiene wizard_config, no se puede hacer merge', self::LOG_CATEGORY);
        }

        Yii::info(
            'Resultado del merge - tiene steps: '
            . (isset($result['wizard_config']['steps']) ? 'sí (' . count($result['wizard_config']['steps']) . ')' : 'no')
            . ', tiene fields: '
            . (isset($result['wizard_config']['fields']) ? 'sí (' . count($result['wizard_config']['fields']) . ')' : 'no'),
            self::LOG_CATEGORY
        );

        return $result;
    }

    /**
     * Merge de ui_meta: `clients` se fusiona por clave (específico pisa común); el resto merge recursivo.
     */
    private static function mergeUiMeta($base, $override)
    {
        if (!is_array($override)) {
            return $override;
        }
        if (!is_array($base)) {
            $base = [];
        }

        $mergedClients = [];
        if (isset($base['clients']) && is_array($base['clients'])) {
            $mergedClients = $base['clients'];
        }
        if (isset($override['clients']) && is_array($override['clients'])) {
            $mergedClients = array_merge($mergedClients, $override['clients']);
        }

        $out = self::mergeArrays($base, $override);
        if ($mergedClients !== []) {
            $out['clients'] = $mergedClients;
        }

        return $out;
    }

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

    private static function processVariables($config, $params)
    {
        $paramAliases = [
            'id_servicio' => 'id_servicio_asignado',
            'servicio_actual' => 'id_servicio_asignado',
            'servicio' => 'id_servicio_asignado',
            'id_rr_hh' => 'id_rr_hh',
            'id_rrhh' => 'id_rr_hh',
            'rrhh' => 'id_rr_hh',
            'profesional' => 'id_rr_hh',
        ];

        foreach ($paramAliases as $alias => $realName) {
            if (isset($params[$alias]) && !isset($params[$realName])) {
                $params[$realName] = $params[$alias];
                if (YII_DEBUG) {
                    Yii::info("Mapeando parámetro alias '{$alias}' -> '{$realName}' con valor: " . $params[$alias], self::LOG_CATEGORY);
                }
            }
        }

        if (YII_DEBUG) {
            Yii::info('Parámetros recibidos en processVariables: ' . json_encode($params), self::LOG_CATEGORY);
        }

        if (isset($config['wizard_config']['fields'])) {
            foreach ($config['wizard_config']['fields'] as &$field) {
                $fieldName = $field['name'] ?? null;

                if ($fieldName && isset($params[$fieldName]) && $params[$fieldName] !== null && $params[$fieldName] !== '') {
                    $field['value'] = $params[$fieldName];
                    if (YII_DEBUG) {
                        Yii::info("Inyectando valor '{$params[$fieldName]}' en campo '{$fieldName}'", self::LOG_CATEGORY);
                    }
                }

                if (isset($field['min']) && $field['min'] === 'today') {
                    $field['min'] = $params['today'] ?? date('Y-m-d');
                }
                if (isset($field['max']) && $field['max'] === 'today') {
                    $field['max'] = $params['today'] ?? date('Y-m-d');
                }

                if (isset($field['type']) && $field['type'] === 'select') {
                    if (isset($field['options']) && $field['options'] === '{{options}}') {
                        $options = self::getOptionsForField($field, $params);
                        if ($options !== null) {
                            $field['options'] = $options;
                        } else {
                            unset($field['options']);
                        }
                    }
                }
            }
        }

        return $config;
    }

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

        if ($dependsOn && !isset($params[$dependsOn])) {
            if ($source === 'servicios' && $filter === 'efector_servicios') {
                // continuar
            } else {
                return null;
            }
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
                default:
                    Yii::warning("Fuente de opciones no soportada: {$source}", self::LOG_CATEGORY);
                    return null;
            }
        } catch (\Exception $e) {
            Yii::error("Error obteniendo opciones para campo {$field['name']}: " . $e->getMessage(), self::LOG_CATEGORY);
            return null;
        }

        return $options;
    }

    private static function getEfectoresOptions($filter, $params)
    {
        $userId = Yii::$app->user->id ?? null;

        if ($filter === 'user_efectores' && $userId) {
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
        }

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

    private static function getServiciosOptions($filter, $params)
    {
        if ($filter === 'efector_servicios' && isset($params['id_efector']) && $params['id_efector'] !== null && $params['id_efector'] !== '') {
            $servicios = \common\models\ServiciosEfector::find()
                ->joinWith('idServicio')
                ->where(['servicios_efector.id_efector' => $params['id_efector']])
                ->andWhere('servicios.deleted_at IS NULL')
                ->orderBy('servicios.nombre')
                ->all();

            $options = [];
            foreach ($servicios as $servicioEfector) {
                $options[] = [
                    'id' => (string) $servicioEfector->idServicio->id_servicio,
                    'name' => $servicioEfector->idServicio->nombre,
                ];
            }
            return $options;
        }

        $servicios = \common\models\Servicio::find()
            ->orderBy('nombre')
            ->all();

        $options = [];
        foreach ($servicios as $servicio) {
            $options[] = [
                'id' => (string) $servicio->id_servicio,
                'name' => $servicio->nombre,
            ];
        }
        return $options;
    }

    private static function getRrhhOptions($filter, $params)
    {
        return [];
    }

    private static function calculateInitialStep($wizardSteps, $fieldsConfig, $providedParams)
    {
        if (empty($wizardSteps)) {
            return 0;
        }

        if (empty($providedParams)) {
            return 0;
        }

        $fieldsMap = [];
        foreach ($fieldsConfig as $field) {
            $fieldName = $field['name'] ?? null;
            if (!empty($fieldName)) {
                $fieldsMap[$fieldName] = $field;
            }
        }

        foreach ($wizardSteps as $stepIndex => $step) {
            $stepFields = $step['fields'] ?? [];
            $stepComplete = true;

            foreach ($stepFields as $field) {
                $fieldName = is_array($field) ? ($field['name'] ?? null) : $field;

                if (empty($fieldName)) {
                    continue;
                }

                $fieldConfig = $fieldsMap[$fieldName] ?? null;

                $isRequired = $fieldConfig['required'] ?? false;
                $hasValue = isset($providedParams[$fieldName])
                    && $providedParams[$fieldName] !== null
                    && $providedParams[$fieldName] !== '';

                if ($isRequired && !$hasValue) {
                    $stepComplete = false;
                    break;
                }
            }

            if (!$stepComplete) {
                return $stepIndex;
            }
        }

        return count($wizardSteps) - 1;
    }
}
