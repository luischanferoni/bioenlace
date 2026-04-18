<?php

namespace common\components\FlowManifest;

use Symfony\Component\Yaml\Yaml;
use Yii;
use yii\helpers\Json;

/**
 * Compila intents YAML → JSON `ui_type=flow` bajo views/json (artefacto versionado).
 */
final class FlowManifestCompiler
{
    private const INTENTS_DIR = __DIR__ . '/../SubIntentEngine/schemas/intents';

    /**
     * @return int código de salida (0 ok)
     */
    public static function run(bool $checkOnly): int
    {
        $files = glob(self::INTENTS_DIR . '/*.yaml') ?: [];
        if ($files === []) {
            fwrite(STDERR, "No hay YAML en " . self::INTENTS_DIR . "\n");
            return 1;
        }
        sort($files);

        foreach ($files as $file) {
            try {
                $yaml = Yaml::parseFile($file);
            } catch (\Throwable $e) {
                fwrite(STDERR, "YAML inválido {$file}: " . $e->getMessage() . "\n");
                return 1;
            }
            if (!is_array($yaml)) {
                fwrite(STDERR, "YAML vacío o no es mapa: {$file}\n");
                return 1;
            }
            $intentId = isset($yaml['intent_id']) ? trim((string) $yaml['intent_id']) : '';
            if ($intentId === '' || preg_match('/^([a-z0-9_-]+)\.(.+)$/i', $intentId, $m) !== 1) {
                fwrite(STDERR, "intent_id inválido en {$file}\n");
                return 1;
            }
            $entity = strtolower((string) $m[1]);
            $action = (string) $m[2];
            $target = Yii::getAlias('@frontend/modules/api/v1/views/json/' . $entity . '/' . $action . '.json');

            $compiled = self::compileIntentArray($yaml, $intentId);
            $json = Json::encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

            if ($checkOnly) {
                if (!is_file($target)) {
                    fwrite(STDERR, "Falta artefacto: {$target}\n");
                    return 1;
                }
                $onDisk = (string) file_get_contents($target);
                if (!self::jsonMapsEqual($json, $onDisk)) {
                    fwrite(STDERR, "Artefacto desactualizado: {$target} (ejecutar sin --check)\n");
                    return 1;
                }
            } else {
                $dir = dirname($target);
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    fwrite(STDERR, "No se pudo crear {$dir}\n");
                    return 1;
                }
                if (file_put_contents($target, $json) === false) {
                    fwrite(STDERR, "No se pudo escribir {$target}\n");
                    return 1;
                }
                echo "OK {$intentId} → {$target}\n";
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $yaml
     * @return array<string, mixed>
     */
    public static function compileIntentArray(array $yaml, string $intentId): array
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
                'assistant_embed' => new \stdClass(),
                'clients' => [
                    '*' => ['min_app_version' => '1.0.0'],
                ],
            ],
            'wizard_config' => self::wizardConfigStub(),
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

    private static function jsonMapsEqual(string $a, string $b): bool
    {
        try {
            $da = Json::decode($a);
            $db = Json::decode($b);
        } catch (\Throwable $e) {
            return false;
        }

        return $da == $db;
    }

    /**
     * @return array<string, mixed>
     */
    private static function wizardConfigStub(): array
    {
        return [
            'title' => 'Reservar turno (flujo por chat)',
            'steps' => [
                [
                    'step' => 0,
                    'title' => 'Guía',
                    'fields' => ['_flow_notice'],
                ],
            ],
            'fields' => [
                [
                    'name' => '_flow_notice',
                    'label' => 'Cómo continuar',
                    'type' => 'textarea',
                    'required' => false,
                    'value' => 'Este flujo ya no es un wizard monolítico: el asistente te va pidiendo cada dato y, cuando corresponda, embebe mini-UIs (por ejemplo, elegir efector). Si ves esto en una pantalla legacy, actualizá el cliente para renderizar `ui_type=flow` + `ui_meta.flow`.',
                ],
            ],
        ];
    }
}
