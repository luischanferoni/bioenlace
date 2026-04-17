<?php

namespace common\components;

use Yii;
use yii\web\ServerErrorHttpException;

/**
 * Helper para endpoints de definiciones de vistas JSON (plantillas en `frontend/modules/api/v1/views/json/...`)
 * expuestos como rutas normales bajo `/api/v1/<entidad>/<accion>`.
 *
 * - GET  => devuelve definición de UI (wizard/list/detail) desde templates JSON (`views/json/...`).
 * - POST => ejecuta submit específico (callable); si falla devuelve la misma UI con `success=false` + `errors` + `values`.
 *
 * Nota: el submit NO se generaliza (cada entidad decide cómo persistir).
 */
final class UiScreenService
{
    /**
     * Screen UI genérico basado en templates (`UiDefinitionTemplateManager`).
     *
     * @param string $entity ej. 'turnos'
     * @param string $action ej. 'crear-como-paciente'
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $postParams
     * @param callable $submit fn(array $post): array{data?: mixed, values?: array, errors?: array}|mixed
     * @return array<string, mixed>
     */
    public static function handleScreen(string $entity, string $action, array $queryParams, array $postParams, callable $submit): array
    {
        $req = Yii::$app->request;
        $actionId = strtolower($entity . '.' . $action);

        if ($req->isPost) {
            try {
                $submitResult = $submit($postParams);
                return [
                    'success' => true,
                    'kind' => 'ui_submit_result',
                    'action_id' => $actionId,
                    'data' => is_array($submitResult) && array_key_exists('data', $submitResult) ? $submitResult['data'] : $submitResult,
                    'errors' => null,
                ];
            } catch (\Throwable $e) {
                $values = $postParams;
                $errors = ['_error' => [$e->getMessage()]];

                $ui = self::renderUiDefinition($entity, $action, $queryParams, $values);
                $ui['success'] = false;
                $ui['errors'] = $errors;
                $ui['values'] = $values;
                $ui['action_id'] = $actionId;

                return $ui;
            }
        }

        return self::renderUiDefinition($entity, $action, $queryParams, null);
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed>|null $values
     * @return array<string, mixed>
     */
    public static function renderUiDefinition(string $entity, string $action, array $queryParams, ?array $values): array
    {
        $params = $queryParams;
        unset($params['r']);
        if ($values !== null) {
            $params = array_merge($params, $values);
        }

        $config = UiDefinitionTemplateManager::render($entity, $action, $params);
        if (!is_array($config) || $config === []) {
            throw new ServerErrorHttpException('La definición de UI tiene un formato inválido.');
        }

        $wizardConfig = isset($config['wizard_config']) && is_array($config['wizard_config']) ? $config['wizard_config'] : null;
        $compat = null;
        $h = UiDefinitionTemplateManager::getClientHeadersFromRequest();
        $rootUiMeta = isset($config['ui_meta']) && is_array($config['ui_meta']) ? $config['ui_meta'] : null;
        if ($rootUiMeta !== null && isset($rootUiMeta['clients']) && is_array($rootUiMeta['clients'])) {
            $compat = UiDefinitionTemplateManager::evaluateClientCompatibility(['ui_meta' => $rootUiMeta], $h['client'], $h['version']);
        } elseif ($wizardConfig !== null) {
            $compat = UiDefinitionTemplateManager::evaluateClientCompatibility($wizardConfig, $h['client'], $h['version']);
        } elseif ($rootUiMeta !== null) {
            $compat = UiDefinitionTemplateManager::evaluateClientCompatibility(['ui_meta' => $rootUiMeta], $h['client'], $h['version']);
        } else {
            $compat = UiDefinitionTemplateManager::evaluateClientCompatibility(['ui_meta' => []], $h['client'], $h['version']);
        }

        $uiType = null;
        if (isset($config['ui_type']) && is_string($config['ui_type']) && $config['ui_type'] !== '') {
            $uiType = $config['ui_type'];
        } elseif ($wizardConfig !== null) {
            $uiType = 'wizard';
        }

        // Normalización: UIs JSON tipo "ui_json" (listados embebibles) se devuelven como lista (no wizard_config),
        // manteniendo `kind=ui_definition` para que el cliente las renderice con su motor.
        if ($uiType === 'ui_json' && isset($config['ui_meta']) && is_array($config['ui_meta']) && isset($config['ui_meta']['list']) && is_array($config['ui_meta']['list'])) {
            $listMeta = $config['ui_meta']['list'];
            $draftField = isset($listMeta['draft_field']) && is_string($listMeta['draft_field']) ? trim($listMeta['draft_field']) : '';

            $fieldsForItems = null;
            if (isset($config['fields']) && is_array($config['fields'])) {
                $fieldsForItems = $config['fields'];
            } elseif ($wizardConfig !== null && isset($wizardConfig['fields']) && is_array($wizardConfig['fields'])) {
                $fieldsForItems = $wizardConfig['fields'];
            }

            $items = [];
            if ($fieldsForItems !== null) {
                foreach ($fieldsForItems as $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $name = isset($f['name']) ? (string) $f['name'] : '';
                    if ($draftField !== '' && $name !== $draftField) {
                        continue;
                    }
                    if (isset($f['options']) && is_array($f['options'])) {
                        $items = $f['options'];
                        break;
                    }
                }
                if ($items === []) {
                    foreach ($fieldsForItems as $f) {
                        if (is_array($f) && isset($f['options']) && is_array($f['options'])) {
                            $items = $f['options'];
                            break;
                        }
                    }
                }
            }

            unset($config['wizard_config']);
            $config['items'] = $items;
            if ($draftField !== '') {
                $config['ui_meta']['list']['draft_field'] = $draftField;
            }
        }

        return array_merge(
            [
                'success' => true,
                'kind' => 'ui_definition',
                'ui_type' => $uiType,
                'action_id' => strtolower($entity . '.' . $action),
                'compatibility' => $compat,
            ],
            $config
        );
    }
}

