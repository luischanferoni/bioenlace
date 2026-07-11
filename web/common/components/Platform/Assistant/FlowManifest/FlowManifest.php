<?php

namespace common\components\Platform\Assistant\FlowManifest;

use common\components\Platform\Assistant\Catalog\DataAccessCatalogIntentSupport;
use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Assistant\Copy\AssistantChannelCopy;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\IntentManifestMetadata;
use Symfony\Component\Yaml\Yaml;
use Yii;
use yii\web\Request;

/**
 * Construye el manifiesto de flujo (tabs, rutas, pasos) **en runtime** desde el YAML del intent.
 * No hay artefactos bajo `views/json` ni paso de compilación previo.
 */
final class FlowManifest
{
    /**
     * @return array<string, mixed>|null
     */
    public static function buildActiveSliceForSubintent(string $intentId, string $activeSubintentId): ?array
    {
        if (DataAccessCatalogIntentSupport::isCatalogOnlyIntent($intentId)) {
            return self::buildCatalogOnlySlice($intentId, $activeSubintentId);
        }

        $root = self::loadRootForIntentId($intentId);
        if ($root === null) {
            return null;
        }
        $rawYaml = self::loadIntentYaml($intentId);
        $actionName = self::displayActionNameForIntent($intentId, $rawYaml);
        $flowMeta = self::flowPresentationMetaForIntent($intentId, $rawYaml);

        $uiMeta = isset($root['ui_meta']) && is_array($root['ui_meta']) ? $root['ui_meta'] : [];
        $flow = isset($uiMeta['flow']) && is_array($uiMeta['flow']) ? $uiMeta['flow'] : [];
        $steps = isset($flow['steps']) && is_array($flow['steps']) ? $flow['steps'] : [];

        // `steps` se usa principalmente para “plan” visual en clientes (lista de labels/orden).
        // Para evitar payload duplicado, devolvemos una versión compacta sin `ui`.
        $stepsCompact = [];
        foreach ($steps as $step) {
            if (!is_array($step) || empty($step['id'])) {
                continue;
            }
            $stepsCompact[] = [
                'id' => AssistantDraftNormalizer::scalarString($step['id'] ?? ''),
                'assistant_text' => AssistantDraftNormalizer::scalarString($step['assistant_text'] ?? ''),
                'requires' => isset($step['requires']) && is_array($step['requires']) ? $step['requires'] : [],
                'provides' => isset($step['provides']) && is_array($step['provides']) ? $step['provides'] : [],
                'next' => AssistantDraftNormalizer::scalarString($step['next'] ?? ''),
            ];
        }

        $activeStep = null;
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            if (AssistantDraftNormalizer::scalarString($step['id'] ?? '') === $activeSubintentId) {
                $activeStep = $step;
                break;
            }
        }

        return [
            'schema_version' => AssistantDraftNormalizer::scalarString($uiMeta['schema_version'] ?? '', '1'),
            'intent_id' => AssistantDraftNormalizer::scalarString($flow['intent_id'] ?? '', $intentId),
            'action_name' => $actionName,
            'operation' => $flowMeta['operation'],
            'crud_tone' => $flowMeta['crud_tone'],
            'draft_keys' => isset($flow['draft_keys']) && is_array($flow['draft_keys']) ? $flow['draft_keys'] : [],
            'entry_subintent_id' => AssistantDraftNormalizer::scalarString($flow['entry_subintent_id'] ?? ''),
            'steps' => $stepsCompact,
            'active_subintent_id' => $activeSubintentId,
            'active_step' => $activeStep,
            // `open_ui_hints` se puede derivar desde YAML; clientes usan `active_step.ui` y/o `open_ui` del payload principal.
        ];
    }

    /**
     * Manifiesto de un paso para intents DataAccess sin YAML (`data-access.info|listar|editar`).
     *
     * @return array<string, mixed>|null
     */
    private static function buildCatalogOnlySlice(string $intentId, string $activeSubintentId): ?array
    {
        $openUiDef = DataAccessCatalogIntentSupport::openUiDefForIntent($intentId);
        if ($openUiDef === null) {
            return null;
        }

        $subintentId = trim($activeSubintentId) !== '' ? trim($activeSubintentId) : 'open';
        $operation = match ($intentId) {
            'data-access.info' => 'info',
            'data-access.listar' => 'list',
            'data-access.editar' => 'edit',
            default => null,
        };
        $label = IntentManifestMetadata::resolveDisplayActionNameForClient(
            DataAccessCatalogIntentSupport::displayLabelForIntent($intentId),
            $operation,
            self::requestAppClientId()
        );
        $text = $label !== '' ? $label : AssistantChannelCopy::t('open_ui_button');

        $actionId = AssistantDraftNormalizer::scalarString($openUiDef['action_id'] ?? '');
        if ($actionId === '') {
            return null;
        }
        $actionId = strtolower($actionId);

        $stepCompact = [
            'id' => 'open',
            'assistant_text' => $text,
            'requires' => [],
            'provides' => [],
            'next' => '',
        ];

        $activeStep = $stepCompact + [
            'ui' => [
                'default_tab' => 'default',
                'tabs' => [
                    [
                        'id' => 'default',
                        'label' => $text,
                        'action_id' => $actionId,
                        'route' => self::routeForActionId($actionId),
                        'params' => self::normalizedParamsMap($openUiDef['params'] ?? null),
                        'requires_client' => [],
                    ],
                ],
            ],
        ];

        return [
            'schema_version' => '1',
            'intent_id' => $intentId,
            'action_name' => $label,
            'operation' => $operation,
            'crud_tone' => IntentManifestMetadata::resolveCrudTone($operation),
            'draft_keys' => [],
            'entry_subintent_id' => 'open',
            'steps' => [$stepCompact],
            'active_subintent_id' => $subintentId,
            'active_step' => $activeStep,
        ];
    }

    /**
     * Raíz del descriptor de flujo (equivalente histórico al JSON `ui_type=flow`, generado solo en memoria).
     *
     * @return array<string, mixed>|null
     */
    private static function loadRootForIntentId(string $intentId): ?array
    {
        $yaml = self::loadIntentYaml($intentId);
        if ($yaml === null || !is_array($yaml)) {
            return null;
        }
        $id = AssistantDraftNormalizer::scalarString($yaml['intent_id'] ?? '', $intentId);

        try {
            return self::buildRootArrayFromIntentYaml($yaml, $id);
        } catch (\Throwable $e) {
            Yii::error('FlowManifest build: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadIntentYaml(string $intentId): ?array
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return null;
        }
        $path = IntentSchemaPaths::resolveFileForIntentId($intentId);
        if ($path === null || !is_file($path)) {
            return null;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::error('YAML inválido intent ' . $intentId . ': ' . $e->getMessage(), __METHOD__);
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $yaml
     * @return array<string, mixed>
     */
    private static function buildRootArrayFromIntentYaml(array $yaml, string $intentId): array
    {
        $subintents = isset($yaml['subintents']) && is_array($yaml['subintents']) ? $yaml['subintents'] : [];
        if ($subintents === []) {
            throw new \InvalidArgumentException('Intent sin subintents');
        }
        $entry = '';
        if (isset($subintents[0]) && is_array($subintents[0]) && !empty($subintents[0]['id'])) {
            $entry = AssistantDraftNormalizer::scalarString($subintents[0]['id'] ?? '');
        }

        $steps = [];
        foreach ($subintents as $sub) {
            if (!is_array($sub) || empty($sub['id'])) {
                continue;
            }
            $steps[] = self::compileStep($sub);
        }

        $draftKeys = self::collectDraftKeys($subintents);
        foreach (self::stringList($yaml['draft_keys_extra'] ?? null) as $k) {
            if ($k !== '' && !in_array($k, $draftKeys, true)) {
                $draftKeys[] = $k;
            }
        }
        $openUiHints = self::compileOpenUiHints($subintents);

        return [
            'ui_type' => 'flow',
            'ui_meta' => [
                'schema_version' => '1',
                'flow' => [
                    'intent_id' => $intentId,
                    'mode' => 'conversational',
                    'entry_subintent_id' => $entry,
                    'draft_keys' => $draftKeys,
                    'open_ui_hints' => $openUiHints,
                    'steps' => $steps,
                ],
                'clients' => [
                    '*' => ['min_app_version' => '1.0.0'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $sub
     * @return array<string, mixed>
     */
    private static function compileStep(array $sub): array
    {
        $id = AssistantDraftNormalizer::scalarString($sub['id'] ?? '');
        if ($id === '') {
            throw new \InvalidArgumentException('Subintent sin id');
        }
        $step = [
            'id' => $id,
            'assistant_text' => AssistantDraftNormalizer::scalarString($sub['assistant_text'] ?? ''),
            'requires' => self::stringList($sub['requires'] ?? null),
            'provides' => self::stringList($sub['provides'] ?? null),
            'next' => self::compileStepNextField($sub),
        ];

        $chooser = isset($sub['chooser']) && is_array($sub['chooser']) ? $sub['chooser'] : null;
        if ($chooser !== null) {
            $nearBranch = isset($chooser['when_user_says_nearby']) && is_array($chooser['when_user_says_nearby'])
                ? $chooser['when_user_says_nearby']
                : null;
            $otherwiseBranch = isset($chooser['otherwise']) && is_array($chooser['otherwise']) ? $chooser['otherwise'] : null;
            $nearOpen = is_array($nearBranch) && isset($nearBranch['open_ui']) && is_array($nearBranch['open_ui'])
                ? $nearBranch['open_ui']
                : null;
            $otherwiseOpen = is_array($otherwiseBranch) && isset($otherwiseBranch['open_ui']) && is_array($otherwiseBranch['open_ui'])
                ? $otherwiseBranch['open_ui']
                : null;

            $tabs = [];
            if (is_array($otherwiseOpen) && !empty($otherwiseOpen['action_id'])) {
                $aid = strtolower(AssistantDraftNormalizer::scalarString($otherwiseOpen['action_id'] ?? ''));
                $tabs[] = [
                    'id' => 'default',
                    'label' => 'Lista',
                    'action_id' => $aid,
                    'route' => self::routeForActionId($aid),
                    'params' => self::normalizedParamsMap($otherwiseOpen['params'] ?? null),
                    'requires_client' => [],
                ];
            }
            if (is_array($nearOpen) && !empty($nearOpen['action_id'])) {
                $aid = strtolower(AssistantDraftNormalizer::scalarString($nearOpen['action_id'] ?? ''));
                $params = self::paramsAssoc($nearOpen['params'] ?? null);
                $params['latitud'] = 'client.latitud';
                $params['longitud'] = 'client.longitud';
                $tabs[] = [
                    'id' => 'nearby',
                    'label' => 'Cerca',
                    'action_id' => $aid,
                    'route' => self::routeForActionId($aid),
                    'params' => $params === [] ? new \stdClass() : $params,
                    'requires_client' => ['geolocation'],
                ];
            }
            $step['ui'] = [
                'default_tab' => 'default',
                'tabs' => $tabs,
            ];

            return $step;
        }

        $direct = isset($sub['open_ui']) && is_array($sub['open_ui']) ? $sub['open_ui'] : null;
        if (is_array($direct) && !empty($direct['action_id'])) {
            $aid = strtolower(AssistantDraftNormalizer::scalarString($direct['action_id'] ?? ''));
            $clientOpen = isset($direct['client_open']) && is_array($direct['client_open'])
                ? $direct['client_open']
                : \common\components\Platform\Assistant\Catalog\UiActionCatalogProviderRegistry::clientOpenForActionId($aid);
            $isNative = is_array($clientOpen) && ($clientOpen['kind'] ?? '') === 'native';
            $tab = [
                'id' => 'default',
                'label' => 'Elegir',
                'action_id' => $aid,
                'route' => $isNative ? '' : self::routeForActionId($aid),
                'params' => self::normalizedParamsMap($direct['params'] ?? null),
                'requires_client' => [],
            ];
            if (is_array($clientOpen)) {
                $tab['client_open'] = $clientOpen;
            }
            $step['ui'] = [
                'default_tab' => 'default',
                'tabs' => [$tab],
            ];
        } else {
            $step['ui'] = [
                'default_tab' => 'default',
                'tabs' => [],
            ];
        }

        $composerCfg = isset($sub['composer_capture']) && is_array($sub['composer_capture'])
            ? $sub['composer_capture']
            : null;
        if ($composerCfg !== null) {
            $field = AssistantDraftNormalizer::scalarString($composerCfg['draft_field'] ?? '');
            $submitActionId = AssistantDraftNormalizer::scalarString($composerCfg['submit_action_id'] ?? '');
            $route = $submitActionId !== '' ? self::routeForActionId($submitActionId) : '';
            if ($field !== '' && $route !== '') {
                $paramsMap = isset($composerCfg['params']) && is_array($composerCfg['params'])
                    ? self::normalizedParamsMap($composerCfg['params'])
                    : new \stdClass();
                $step['composer_capture'] = [
                    'draft_field' => $field,
                    'placeholder' => trim((string) ($composerCfg['placeholder'] ?? '')),
                    'min_length' => max(1, (int) ($composerCfg['min_length'] ?? 1)),
                    'action_id' => $submitActionId,
                    'route' => $route,
                    'method' => 'POST',
                    'body_template' => $paramsMap,
                ];
                $step['ui'] = [
                    'default_tab' => 'default',
                    'tabs' => [],
                ];
            }
        }

        return $step;
    }

    /**
     * Campo `next` en el manifiesto: `next` explícito del YAML o, si solo hay `next_routing`,
     * el `next` de la regla `when.default: true` (fallback) o la primera regla con `next`.
     *
     * @param array<string, mixed> $sub
     */
    private static function compileStepNextField(array $sub): string
    {
        if (isset($sub['next'])) {
            $n = AssistantDraftNormalizer::scalarString($sub['next'] ?? '');
            if ($n !== '') {
                return $n;
            }
        }
        $routing = isset($sub['next_routing']) && is_array($sub['next_routing']) ? $sub['next_routing'] : null;
        if ($routing === null) {
            return '';
        }
        $defaultNext = '';
        foreach ($routing as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $when = isset($rule['when']) && is_array($rule['when']) ? $rule['when'] : null;
            if ($when !== null && isset($when['default']) && $when['default'] === true) {
                $nn = AssistantDraftNormalizer::scalarString($rule['next'] ?? '');
                if ($nn !== '') {
                    $defaultNext = $nn;
                }
            }
        }
        if ($defaultNext !== '') {
            return $defaultNext;
        }
        foreach ($routing as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $nn = AssistantDraftNormalizer::scalarString($rule['next'] ?? '');
            if ($nn !== '') {
                return $nn;
            }
        }

        return '';
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function stringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (is_string($v) && trim($v) !== '') {
                $out[] = trim($v);
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function paramsAssoc($params): array
    {
        $out = [];
        if (is_array($params)) {
            foreach ($params as $k => $v) {
                $key = is_string($k) ? trim($k) : '';
                if ($key === '') {
                    continue;
                }
                if (is_string($v)) {
                    $out[$key] = trim($v);
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $rawYaml
     */
    private static function displayActionNameForIntent(string $intentId, ?array $rawYaml): string
    {
        $appClientId = self::requestAppClientId();
        $indexed = IntentManifestIndex::get($intentId);
        if ($indexed !== null) {
            $base = trim((string) ($indexed['action_name_base'] ?? ''));
            if ($base === '') {
                $base = trim((string) ($indexed['action_name'] ?? ''));
            }
            $operation = isset($indexed['operation']) ? (string) $indexed['operation'] : null;

            return IntentManifestMetadata::resolveDisplayActionNameForClient($base, $operation, $appClientId);
        }

        $base = '';
        if (is_array($rawYaml) && isset($rawYaml['action_name'])) {
            $base = AssistantDraftNormalizer::scalarString($rawYaml['action_name']);
        }
        $flowMeta = self::flowPresentationMetaForIntent($intentId, $rawYaml);

        return IntentManifestMetadata::resolveDisplayActionNameForClient(
            $base,
            $flowMeta['operation'],
            $appClientId
        );
    }

    private static function requestAppClientId(): ?string
    {
        try {
            if (!Yii::$app->has('request')) {
                return null;
            }
            $request = Yii::$app->request;
            if (!$request instanceof Request) {
                return null;
            }
            $id = trim((string) $request->headers->get('X-App-Client', ''));

            return $id !== '' ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed>|null $rawYaml
     * @return array{operation: ?string, crud_tone: string}
     */
    private static function flowPresentationMetaForIntent(string $intentId, ?array $rawYaml): array
    {
        $indexed = IntentManifestIndex::get($intentId);
        if ($indexed !== null) {
            return [
                'operation' => isset($indexed['operation']) ? (string) $indexed['operation'] : null,
                'crud_tone' => trim((string) ($indexed['crud_tone'] ?? '')),
            ];
        }

        $path = IntentSchemaPaths::resolveFileForIntentId($intentId);
        $category = $path !== null ? IntentSchemaPaths::categoryFromPath($path) : null;
        $operation = is_array($rawYaml)
            ? IntentManifestMetadata::resolveOperation($category, $rawYaml)
            : null;

        return [
            'operation' => $operation,
            'crud_tone' => IntentManifestMetadata::resolveCrudTone($operation),
        ];
    }

    /**
     * @param mixed $params
     * @return array<string, string>|\stdClass
     */
    private static function normalizedParamsMap($params)
    {
        $out = self::paramsAssoc($params);

        return $out === [] ? new \stdClass() : $out;
    }

    private static function routeForActionId(string $actionId): string
    {
        $actionId = strtolower(trim($actionId));
        $clinicalRoute = \common\components\Platform\Assistant\Catalog\UiActionCatalogProviderRegistry::httpRouteForActionId($actionId);
        if ($clinicalRoute !== '') {
            return $clinicalRoute;
        }
        $dataAccessRoute = \common\components\Platform\Assistant\Catalog\DataAccessUiActionCatalog::httpRouteForActionId($actionId);
        if ($dataAccessRoute !== '') {
            return $dataAccessRoute;
        }
        $p = strpos($actionId, '.');
        if ($p === false) {
            return '';
        }
        $entity = substr($actionId, 0, $p);
        $action = substr($actionId, $p + 1);

        return '/api/v1/' . rawurlencode($entity) . '/' . rawurlencode($action);
    }

    /**
     * @param list<mixed> $subintents
     * @return list<string>
     */
    private static function collectDraftKeys(array $subintents): array
    {
        $keys = [];
        foreach ($subintents as $sub) {
            if (!is_array($sub)) {
                continue;
            }
            foreach (['requires', 'provides'] as $blk) {
                foreach (self::stringList($sub[$blk] ?? null) as $req) {
                    if (strncmp($req, 'draft.', 6) === 0) {
                        $keys[] = substr($req, 6);
                    }
                }
            }
        }
        $keys = array_values(array_unique(array_filter($keys)));

        return $keys;
    }

    /**
     * @param list<mixed> $subintents
     * @return array<string, array{action_id: string}>
     */
    private static function compileOpenUiHints(array $subintents): array
    {
        $hints = [];
        foreach ($subintents as $sub) {
            if (!is_array($sub) || empty($sub['id'])) {
                continue;
            }
            $sid = AssistantDraftNormalizer::scalarString($sub['id'] ?? '');
            if ($sid === '') {
                continue;
            }
            $chooser = isset($sub['chooser']) && is_array($sub['chooser']) ? $sub['chooser'] : null;
            if ($chooser !== null) {
                $nearBranch = isset($chooser['when_user_says_nearby']) && is_array($chooser['when_user_says_nearby'])
                    ? $chooser['when_user_says_nearby']
                    : null;
                $otherwiseBranch = isset($chooser['otherwise']) && is_array($chooser['otherwise']) ? $chooser['otherwise'] : null;
                $nearOpen = is_array($nearBranch) && isset($nearBranch['open_ui']) && is_array($nearBranch['open_ui'])
                    ? $nearBranch['open_ui']
                    : null;
                $otherwiseOpen = is_array($otherwiseBranch) && isset($otherwiseBranch['open_ui']) && is_array($otherwiseBranch['open_ui'])
                    ? $otherwiseBranch['open_ui']
                    : null;
                if (is_array($otherwiseOpen) && !empty($otherwiseOpen['action_id'])) {
                    $hints[$sid] = ['action_id' => strtolower(AssistantDraftNormalizer::scalarString($otherwiseOpen['action_id'] ?? ''))];
                }
                if (is_array($nearOpen) && !empty($nearOpen['action_id'])) {
                    $nearKey = $sid . '_nearby';
                    $hints[$nearKey] = ['action_id' => strtolower(AssistantDraftNormalizer::scalarString($nearOpen['action_id'] ?? ''))];
                }
                continue;
            }
            $direct = isset($sub['open_ui']) && is_array($sub['open_ui']) ? $sub['open_ui'] : null;
            if (is_array($direct) && !empty($direct['action_id'])) {
                $hints[$sid] = ['action_id' => strtolower(AssistantDraftNormalizer::scalarString($direct['action_id'] ?? ''))];
            }
        }

        return $hints;
    }
}
