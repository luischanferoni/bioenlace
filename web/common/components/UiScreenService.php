<?php

namespace common\components;

use Yii;
use yii\web\ServerErrorHttpException;

/**
 * Helper para endpoints de UI JSON (screens) bajo `/api/v1/ui/...`.
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
        if ($wizardConfig !== null) {
            $h = UiDefinitionTemplateManager::getClientHeadersFromRequest();
            $compat = UiDefinitionTemplateManager::evaluateClientCompatibility($wizardConfig, $h['client'], $h['version']);
        }

        return array_merge(
            [
                'success' => true,
                'kind' => 'ui_definition',
                'ui_type' => $wizardConfig !== null ? 'wizard' : null,
                'action_id' => strtolower($entity . '.' . $action),
                'compatibility' => $compat,
            ],
            $config
        );
    }
}

