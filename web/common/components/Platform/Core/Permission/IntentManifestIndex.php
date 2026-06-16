<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Core\Permission\IntentPermissionResolver;
use Symfony\Component\Yaml\Yaml;

/**
 * Índice en memoria de manifiestos YAML de intents (permiso, pasos open_ui, flow_submit).
 */
final class IntentManifestIndex
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $byIntentId = null;

    /** @var array<string, list<array{intent_id: string, step_id: string, permission: string, rbac_route: string}>>|null */
    private static ?array $byOpenUiActionId = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        self::ensureBuilt();

        return self::$byIntentId ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $intentId): ?array
    {
        self::ensureBuilt();
        $intentId = trim($intentId);

        return self::$byIntentId[$intentId] ?? null;
    }

    /**
     * Intents que declaran un paso open_ui con este action_id.
     *
     * @return list<array{intent_id: string, step_id: string, permission: string, rbac_route: string}>
     */
    public static function parentIntentsForOpenUiAction(string $actionId): array
    {
        self::ensureBuilt();
        $actionId = trim($actionId);

        return self::$byOpenUiActionId[$actionId] ?? [];
    }

    /**
     * Clave RBAC del intent: siempre el intent_id.
     */
    public static function permissionKeyForIntent(string $intentId): string
    {
        return trim($intentId);
    }

    public static function rbacRouteForIntent(string $intentId): string
    {
        $row = self::get($intentId);
        if ($row === null) {
            return '';
        }

        return trim((string) ($row['rbac_route'] ?? ''));
    }

    public static function resetCache(): void
    {
        self::$byIntentId = null;
        self::$byOpenUiActionId = null;
        IntentSchemaPaths::resetIndexCache();
    }

    private static function ensureBuilt(): void
    {
        if (self::$byIntentId !== null) {
            return;
        }

        self::$byIntentId = [];
        self::$byOpenUiActionId = [];

        $seenIds = [];
        foreach (IntentSchemaPaths::discoverYamlFiles() as $path) {
            $fileIntentId = IntentSchemaPaths::intentIdFromPath($path);
            try {
                $data = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($data)) {
                continue;
            }

            $intentId = trim((string) ($data['intent_id'] ?? $fileIntentId));
            if ($intentId === '') {
                continue;
            }

            if (isset($seenIds[$intentId])) {
                continue;
            }
            $seenIds[$intentId] = $path;

            $permission = IntentPermissionResolver::resolve($intentId, $data);
            $rbacRoute = trim((string) ($data['rbac_route'] ?? ''));
            if ($rbacRoute !== '') {
                $rbacRoute = '/' . ltrim($rbacRoute, '/');
            }
            $category = IntentSchemaPaths::categoryFromPath($path);

            $openUiSteps = self::extractOpenUiSteps($data);
            $flowSubmit = is_array($data['flow_submit'] ?? null) ? $data['flow_submit'] : null;
            $operation = IntentManifestMetadata::resolveOperation($category, $data);

            self::$byIntentId[$intentId] = [
                'intent_id' => $intentId,
                'path' => $path,
                'category' => $category,
                'permission' => $permission,
                'rbac_route' => $rbacRoute,
                'action_name' => trim((string) ($data['action_name'] ?? '')),
                'operation' => $operation,
                'intent_family' => trim((string) ($data['intent_family'] ?? '')),
                'domain_operation' => trim((string) ($data['domain_operation'] ?? '')),
                'subject_resolution' => is_array($data['subject_resolution'] ?? null)
                    ? $data['subject_resolution']
                    : null,
                'fields' => IntentManifestMetadata::extractFieldNames($data),
                'field_groups' => is_array($data['field_groups'] ?? null) ? $data['field_groups'] : null,
                'keywords' => is_array($data['keywords'] ?? null) ? $data['keywords'] : [],
                'open_ui_steps' => $openUiSteps,
                'flow_submit' => $flowSubmit,
                'uses_extended_contract' => IntentManifestMetadata::usesExtendedContract($data),
            ];

            foreach ($openUiSteps as $step) {
                $actionId = trim((string) ($step['action_id'] ?? ''));
                if ($actionId === '') {
                    continue;
                }
                self::$byOpenUiActionId[$actionId][] = [
                    'intent_id' => $intentId,
                    'step_id' => trim((string) ($step['step_id'] ?? '')),
                    'permission' => $permission,
                    'rbac_route' => $rbacRoute,
                ];
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{step_id: string, action_id: string, source: string}>
     */
    private static function extractOpenUiSteps(array $data): array
    {
        $out = [];
        $subintents = $data['subintents'] ?? null;
        if (!is_array($subintents)) {
            return $out;
        }

        foreach ($subintents as $sub) {
            if (!is_array($sub)) {
                continue;
            }
            $stepId = trim((string) ($sub['id'] ?? ''));
            $out = array_merge($out, self::collectOpenUiFromNode($sub, $stepId));
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $node
     * @return list<array{step_id: string, action_id: string, source: string}>
     */
    private static function collectOpenUiFromNode(array $node, string $stepId): array
    {
        $out = [];
        $openUi = $node['open_ui'] ?? null;
        if (is_array($openUi)) {
            $actionId = trim((string) ($openUi['action_id'] ?? ''));
            if ($actionId !== '') {
                $out[] = [
                    'step_id' => $stepId,
                    'action_id' => $actionId,
                    'source' => 'open_ui',
                ];
            }
        }

        $chooser = $node['chooser'] ?? null;
        if (is_array($chooser)) {
            foreach (['when_user_says_nearby', 'otherwise'] as $branch) {
                $branchDef = $chooser[$branch] ?? null;
                if (!is_array($branchDef)) {
                    continue;
                }
                $branchOpenUi = $branchDef['open_ui'] ?? null;
                if (!is_array($branchOpenUi)) {
                    continue;
                }
                $actionId = trim((string) ($branchOpenUi['action_id'] ?? ''));
                if ($actionId !== '') {
                    $out[] = [
                        'step_id' => $stepId,
                        'action_id' => $actionId,
                        'source' => 'chooser.' . $branch,
                    ];
                }
            }
        }

        return $out;
    }
}
