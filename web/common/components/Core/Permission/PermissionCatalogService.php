<?php

namespace common\components\Core\Permission;

use common\components\Assistant\Catalog\IntentSchemaPaths;
use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\Permission\IntentPermissionResolver;
use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo unificado de permisos declarativos (intents + atributos data-access-config).
 */
final class PermissionCatalogService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listIntents(): array
    {
        $rows = [];
        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            $rows[] = [
                'kind' => 'intent',
                'key' => $this->intentPermissionKey($intentId, $meta),
                'intent_id' => $intentId,
                'category' => $meta['category'] ?? null,
                'action_name' => $meta['action_name'] ?? '',
                'rbac_route' => $meta['rbac_route'] ?? '',
                'permission' => $meta['permission'] ?? '',
                'open_ui_steps' => $meta['open_ui_steps'] ?? [],
                'path' => $meta['path'] ?? '',
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['intent_id'], (string) $b['intent_id']));

        return $rows;
    }

    /**
     * Atributos declarados para grants read/info/edit (data-access-config).
     *
     * @return list<array<string, mixed>>
     */
    public function listAttributes(): array
    {
        $rows = [];
        $dir = realpath(AttributeGroupCatalog::configDirectory());
        if ($dir === false) {
            return [];
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [] as $path) {
            if (basename($path) === 'manifest.yaml') {
                continue;
            }
            try {
                $chunk = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($chunk)) {
                continue;
            }
            $entity = trim((string) ($chunk['entity'] ?? ''));
            if ($entity === '') {
                continue;
            }

            $rows = array_merge($rows, $this->attributesFromExplicitMap($entity, $chunk));
            $rows = array_merge($rows, $this->attributesFromLegacyGroups($entity, $chunk));
            $rows = array_merge($rows, $this->attributesFromEdit($entity, $chunk));
            $rows = array_merge($rows, $this->attributesFromInfoList($entity, $chunk));
        }

        usort($rows, static fn (array $a, array $b): int => strcmp(
            (string) ($a['key'] ?? ''),
            (string) ($b['key'] ?? '')
        ));

        return $rows;
    }

    /**
     * Pasos open_ui agrupados por intent (heredan permiso del intent padre).
     *
     * @return list<array<string, mixed>>
     */
    public function listFlowStepDependencies(): array
    {
        $rows = [];
        foreach ($this->listIntents() as $intent) {
            $intentId = (string) ($intent['intent_id'] ?? '');
            $permission = (string) ($intent['key'] ?? '');
            foreach ($intent['open_ui_steps'] as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $rows[] = [
                    'intent_id' => $intentId,
                    'step_id' => $step['step_id'] ?? '',
                    'action_id' => $step['action_id'] ?? '',
                    'source' => $step['source'] ?? '',
                    'inherits_permission' => $permission,
                    'api_route' => $this->actionIdToApiRoute((string) ($step['action_id'] ?? '')),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function intentPermissionKey(string $intentId, array $meta): string
    {
        return IntentPermissionResolver::resolve($intentId, $meta);
    }

    /**
     * @param array<string, mixed> $chunk
     * @return list<array<string, mixed>>
     */
    private function attributesFromExplicitMap(string $entity, array $chunk): array
    {
        $rows = [];
        $attributes = $chunk['attributes'] ?? null;
        if (!is_array($attributes)) {
            return $rows;
        }

        foreach ($attributes as $attrName => $def) {
            if (!is_string($attrName) || !is_array($def)) {
                continue;
            }
            $name = trim($attrName);
            if ($name === '') {
                continue;
            }
            foreach (['read', 'info', 'edit'] as $op) {
                if (!empty($def[$op])) {
                    $rows[] = $this->attributeRow($entity, $name, $op, 'attributes');
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $chunk
     * @return list<array<string, mixed>>
     */
    private function attributesFromLegacyGroups(string $entity, array $chunk): array
    {
        $rows = [];
        $groups = $chunk['groups'] ?? null;
        if (!is_array($groups)) {
            return $rows;
        }

            foreach ($groups as $groupKey => $def) {
                if (!is_string($groupKey) || !is_array($def)) {
                    continue;
                }
                $attrNames = array_is_list($def)
                    ? $def
                    : (is_array($def['attributes'] ?? null) ? $def['attributes'] : []);
                foreach ($attrNames as $attrName) {
                    $name = trim((string) $attrName);
                    if ($name === '') {
                        continue;
                    }
                    $rows[] = $this->attributeRow($entity, $name, 'read', 'groups.' . $groupKey);
                }
            }

        return $rows;
    }

    /**
     * @param array<string, mixed> $chunk
     * @return list<array<string, mixed>>
     */
    private function attributesFromEdit(string $entity, array $chunk): array
    {
        $rows = [];
        $edit = $chunk['edit']['attributes'] ?? null;
        if (!is_array($edit)) {
            return $rows;
        }

        foreach ($edit as $aspectId => $def) {
            if (!is_string($aspectId) || !is_array($def)) {
                continue;
            }
            $uiAction = trim((string) ($def['ui_action'] ?? ''));
            if ($uiAction !== '') {
                $rows[] = [
                    'kind' => 'attribute_edit_flow',
                    'key' => $entity . '.' . $aspectId . '.edit',
                    'entity' => $entity,
                    'attribute' => $aspectId,
                    'operation' => 'edit',
                    'source' => 'edit.attributes (open_ui → intent)',
                    'ui_action' => $uiAction,
                ];
                continue;
            }

            $fields = $def['fields'] ?? [$aspectId];
            if (!is_array($fields)) {
                $fields = [$aspectId];
            }
            foreach ($fields as $fieldName) {
                $name = trim((string) $fieldName);
                if ($name === '' || $name === 'weekly_scheduler_widget') {
                    continue;
                }
                $rows[] = $this->attributeRow($entity, $name, 'edit', 'edit.attributes.' . $aspectId);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $chunk
     * @return list<array<string, mixed>>
     */
    private function attributesFromInfoList(string $entity, array $chunk): array
    {
        $rows = [];
        $infoList = $chunk['info_list'] ?? null;
        if (!is_array($infoList)) {
            return $rows;
        }

        foreach ($infoList as $metricId => $def) {
            if (!is_string($metricId) || !is_array($def)) {
                continue;
            }
            foreach (['required_groups', 'read_groups', 'optional_filter_groups'] as $listKey) {
                $groups = $def[$listKey] ?? null;
                if (!is_array($groups)) {
                    continue;
                }
                foreach ($groups as $groupRef) {
                    $ref = trim((string) $groupRef);
                    if ($ref === '') {
                        continue;
                    }
                    $attr = $this->attributeFromGroupRef($ref);
                    if ($attr !== null) {
                        $op = $listKey === 'optional_filter_groups' ? 'info' : 'info';
                        $rows[] = $this->attributeRow($attr['entity'], $attr['attribute'], $op, 'info_list.' . $metricId);
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @return array{entity: string, attribute: string}|null
     */
    private function attributeFromGroupRef(string $groupRef): ?array
    {
        if (strpos($groupRef, '.') === false) {
            return null;
        }
        $parts = explode('.', $groupRef, 2);
        $entity = trim($parts[0]);
        $group = trim($parts[1] ?? '');
        if ($entity === '' || $group === '') {
            return null;
        }

        return ['entity' => $entity, 'attribute' => $group];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributeRow(string $entity, string $attribute, string $operation, string $source): array
    {
        return [
            'kind' => 'attribute',
            'key' => $entity . '.' . $attribute . '.' . $operation,
            'entity' => $entity,
            'attribute' => $attribute,
            'operation' => $operation,
            'source' => $source,
        ];
    }

    private function actionIdToApiRoute(string $actionId): string
    {
        $actionId = trim($actionId);
        if ($actionId === '' || strpos($actionId, '.') === false) {
            return '';
        }
        [$entity, $action] = explode('.', $actionId, 2);

        return '/api/' . $entity . '/' . $action;
    }
}
