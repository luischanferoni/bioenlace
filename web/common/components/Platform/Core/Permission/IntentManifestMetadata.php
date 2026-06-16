<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Contrato YAML extendido para intents read/list/edit (fase RBAC unificado).
 */
final class IntentManifestMetadata
{
    /** @var list<string> */
    public const OPERATIONS = ['create', 'read', 'list', 'edit', 'info'];

    /**
     * @param array<string, mixed> $data
     */
    public static function usesExtendedContract(array $data): bool
    {
        foreach (['operation', 'intent_family', 'domain_operation', 'metric_id', 'edit_surface_id', 'subject_resolution', 'fields', 'field_groups'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function resolveOperation(?string $category, array $data): ?string
    {
        $explicit = trim((string) ($data['operation'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        return match ($category) {
            IntentSchemaPaths::CATEGORY_CREATE => 'create',
            IntentSchemaPaths::CATEGORY_READ => 'read',
            IntentSchemaPaths::CATEGORY_UPDATE => 'edit',
            IntentSchemaPaths::CATEGORY_DELETE => null,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public static function extractFieldNames(array $data): array
    {
        $fields = $data['fields'] ?? null;
        if (!is_array($fields)) {
            return [];
        }

        $names = [];
        foreach ($fields as $field) {
            if (is_string($field)) {
                $name = trim($field);
            } elseif (is_array($field)) {
                $name = trim((string) ($field['name'] ?? $field['attribute'] ?? ''));
            } else {
                continue;
            }
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array<string, mixed> $data
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function validate(string $intentId, ?string $category, array $data): array
    {
        $errors = [];
        $warnings = [];

        if (!self::usesExtendedContract($data)) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $operation = self::resolveOperation($category, $data);
        $explicitOperation = trim((string) ($data['operation'] ?? ''));
        if ($explicitOperation !== '' && !in_array($explicitOperation, self::OPERATIONS, true)) {
            $errors[] = 'Intent «' . $intentId . '»: operation «' . $explicitOperation . '» inválida';
        }

        $intentFamily = trim((string) ($data['intent_family'] ?? ''));
        if ($intentFamily === '') {
            $warnings[] = 'Intent «' . $intentId . '»: contrato extendido sin intent_family';
        }

        $domainOperation = trim((string) ($data['domain_operation'] ?? ''));
        if ($domainOperation === '') {
            $warnings[] = 'Intent «' . $intentId . '»: contrato extendido sin domain_operation';
        } elseif (!self::isKnownDomainOperation($domainOperation)) {
            $errors[] = 'Intent «' . $intentId . '»: domain_operation «' . $domainOperation . '» no existe en domain-operation-policies.yaml';
        }

        if ($operation !== null && $explicitOperation !== '' && $operation !== $explicitOperation) {
            $errors[] = 'Intent «' . $intentId . '»: operation explícita no coincide con la inferida por carpeta';
        }

        $fieldNames = self::extractFieldNames($data);
        $operationForFields = self::resolveOperation($category, $data);
        if ($fieldNames === [] && !in_array($operationForFields, ['info', 'list', 'read'], true)) {
            $warnings[] = 'Intent «' . $intentId . '»: contrato extendido sin fields';
        }

        $metricId = trim((string) ($data['metric_id'] ?? ''));
        if ($metricId !== '' && in_array($operationForFields, ['info', 'list'], true)) {
            if (!self::isKnownMetricId($metricId)) {
                $errors[] = 'Intent «' . $intentId . '»: metric_id «' . $metricId . '» no registrada en data-access-config';
            }
        }

        $editSurfaceId = trim((string) ($data['edit_surface_id'] ?? ''));
        if ($editSurfaceId !== '' && in_array($operationForFields, ['edit'], true)) {
            if (!self::isKnownEditSurfaceId($editSurfaceId)) {
                $errors[] = 'Intent «' . $intentId . '»: edit_surface_id «' . $editSurfaceId . '» no registrada en data-access-config';
            }
        }

        $knownFields = array_fill_keys($fieldNames, true);
        $groups = $data['field_groups'] ?? null;
        if (is_array($groups)) {
            foreach ($groups as $groupKey => $def) {
                if (!is_array($def)) {
                    continue;
                }
                $groupFields = is_array($def['fields'] ?? null) ? $def['fields'] : [];
                foreach ($groupFields as $fieldName) {
                    $name = trim((string) $fieldName);
                    if ($name === '') {
                        continue;
                    }
                    if (!isset($knownFields[$name])) {
                        $errors[] = 'Intent «' . $intentId . '»: field_groups «' . $groupKey . '» referencia campo «' . $name . '» no declarado en fields';
                    }
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public static function isKnownDomainOperation(string $domainOperation): bool
    {
        $domainOperation = trim($domainOperation);
        if ($domainOperation === '') {
            return false;
        }

        $configFile = ProductMetadataPaths::domainOperationPoliciesFile();
        if (!is_file($configFile)) {
            return false;
        }

        try {
            $parsed = Yaml::parseFile($configFile);
        } catch (\Throwable $e) {
            return false;
        }

        if (!is_array($parsed)) {
            return false;
        }

        $operations = is_array($parsed['operations'] ?? null) ? $parsed['operations'] : [];
        if (isset($operations[$domainOperation])) {
            return true;
        }

        foreach ($parsed['domain_only_operations'] ?? [] as $item) {
            if (trim((string) $item) === $domainOperation) {
                return true;
            }
        }

        return false;
    }

    public static function isKnownMetricId(string $metricId): bool
    {
        $metricId = trim($metricId);
        if ($metricId === '') {
            return false;
        }

        return (new \common\components\Platform\Core\DataAccess\AttributeGroupCatalog())->getMetric($metricId) !== null;
    }

    public static function isKnownEditSurfaceId(string $surfaceId): bool
    {
        $surfaceId = trim($surfaceId);
        if ($surfaceId === '') {
            return false;
        }

        return (new \common\components\Platform\Core\DataAccess\AttributeGroupCatalog())->getEditSurface($surfaceId) !== null;
    }
}
