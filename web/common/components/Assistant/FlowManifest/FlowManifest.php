<?php

namespace common\components\Assistant\FlowManifest;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Construye el manifiesto de flujo (tabs, rutas, pasos) **en runtime** desde el YAML del intent.
 * No hay artefactos bajo `views/json` ni paso de compilación previo.
 */
final class FlowManifest
{
    private const INTENTS_DIR = __DIR__ . '/../SubIntentEngine/schemas/intents';

    /**
     * @return array<string, mixed>|null
     */
    public static function buildActiveSliceForSubintent(string $intentId, string $activeSubintentId): ?array
    {
        $root = self::loadRootForIntentId($intentId);
        if ($root === null) {
            return null;
        }
        $rawYaml = self::loadIntentYaml($intentId);
        $actionName = '';
        if (is_array($rawYaml) && isset($rawYaml['action_name'])) {
            $actionName = trim((string) $rawYaml['action_name']);
        }

        $uiMeta = isset($root['ui_meta']) && is_array($root['ui_meta']) ? $root['ui_meta'] : [];
        $flow = isset($uiMeta['flow']) && is_array($uiMeta['flow']) ? $uiMeta['flow'] : [];
        $steps = isset($flow['steps']) && is_array($flow['steps']) ? $flow['steps'] : [];

        $activeStep = null;
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            if (($step['id'] ?? '') === $activeSubintentId) {
                $activeStep = $step;
                break;
            }
        }

        return [
            'schema_version' => isset($uiMeta['schema_version']) ? (string) $uiMeta['schema_version'] : '1',
            'intent_id' => isset($flow['intent_id']) ? (string) $flow['intent_id'] : $intentId,
            'action_name' => $actionName,
            'draft_keys' => isset($flow['draft_keys']) && is_array($flow['draft_keys']) ? $flow['draft_keys'] : [],
            'entry_subintent_id' => isset($flow['entry_subintent_id']) ? (string) $flow['entry_subintent_id'] : '',
            'steps' => $steps,
            'active_subintent_id' => $activeSubintentId,
            'active_step' => $activeStep,
            'open_ui_hints' => isset($flow['open_ui_hints']) && is_array($flow['open_ui_hints']) ? $flow['open_ui_hints'] : [],
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
        $id = isset($yaml['intent_id']) ? trim((string) $yaml['intent_id']) : '';
        if ($id === '') {
            $id = $intentId;
        }

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
        $path = self::INTENTS_DIR . '/' . $intentId . '.yaml';
        if (!is_file($path)) {
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
            $entry = (string) $subintents[0]['id'];
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
        $id = (string) $sub['id'];
        $step = [
            'id' => $id,
            'assistant_text' => isset($sub['assistant_text']) ? (string) $sub['assistant_text'] : '',
            'requires' => self::stringList($sub['requires'] ?? null),
            'provides' => self::stringList($sub['provides'] ?? null),
            'next' => isset($sub['next']) ? trim((string) $sub['next']) : '',
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
                $aid = strtolower(trim((string) $otherwiseOpen['action_id']));
                $tabs[] = [
                    'id' => 'por_servicio',
                    'label' => 'Por servicio',
                    'action_id' => $aid,
                    'route' => self::routeForActionId($aid),
                    'params' => self::normalizedParamsMap($otherwiseOpen['params'] ?? null),
                    'requires_client' => [],
                ];
            }
            if (is_array($nearOpen) && !empty($nearOpen['action_id'])) {
                $aid = strtolower(trim((string) $nearOpen['action_id']));
                $params = self::paramsAssoc($nearOpen['params'] ?? null);
                $params['latitud'] = 'client.latitud';
                $params['longitud'] = 'client.longitud';
                $tabs[] = [
                    'id' => 'cercano',
                    'label' => 'Cerca',
                    'action_id' => $aid,
                    'route' => self::routeForActionId($aid),
                    'params' => $params === [] ? new \stdClass() : $params,
                    'requires_client' => ['geolocation'],
                ];
            }
            $step['ui'] = [
                'default_tab' => 'por_servicio',
                'tabs' => $tabs,
            ];

            return $step;
        }

        $direct = isset($sub['open_ui']) && is_array($sub['open_ui']) ? $sub['open_ui'] : null;
        if (is_array($direct) && !empty($direct['action_id'])) {
            $aid = strtolower(trim((string) $direct['action_id']));
            $step['ui'] = [
                'default_tab' => 'default',
                'tabs' => [
                    [
                        'id' => 'default',
                        'label' => 'Elegir',
                        'action_id' => $aid,
                        'route' => self::routeForActionId($aid),
                        'params' => self::normalizedParamsMap($direct['params'] ?? null),
                        'requires_client' => [],
                    ],
                ],
            ];
        } else {
            $step['ui'] = [
                'default_tab' => 'default',
                'tabs' => [],
            ];
        }

        return $step;
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
            $sid = (string) $sub['id'];
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
                    $hints[$sid] = ['action_id' => strtolower(trim((string) $otherwiseOpen['action_id']))];
                }
                if (is_array($nearOpen) && !empty($nearOpen['action_id'])) {
                    $nearKey = $sid === 'select_efector' ? 'select_efector_nearby' : ($sid . '_nearby');
                    $hints[$nearKey] = ['action_id' => strtolower(trim((string) $nearOpen['action_id']))];
                }
                continue;
            }
            $direct = isset($sub['open_ui']) && is_array($sub['open_ui']) ? $sub['open_ui'] : null;
            if (is_array($direct) && !empty($direct['action_id'])) {
                $hints[$sid] = ['action_id' => strtolower(trim((string) $direct['action_id']))];
            }
        }

        return $hints;
    }
}
