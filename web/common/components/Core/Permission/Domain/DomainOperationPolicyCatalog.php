<?php

namespace common\components\Core\Permission\Domain;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo declarativo: operación RBAC → políticas de recurso (YAML).
 */
final class DomainOperationPolicyCatalog
{
    private const CONFIG_FILE = __DIR__
        . '/../../../Assistant/SubIntentEngine/schemas/domain-operation-policies.yaml';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $operations = null;

    /**
     * @return array<string, mixed>|null
     */
    public function getOperationDefinition(string $operationKey): ?array
    {
        $operationKey = trim($operationKey);
        if ($operationKey === '') {
            return null;
        }

        return self::load()[$operationKey] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function load(): array
    {
        if (self::$operations !== null) {
            return self::$operations;
        }

        if (!is_file(self::CONFIG_FILE)) {
            self::$operations = [];

            return self::$operations;
        }

        $parsed = Yaml::parseFile(self::CONFIG_FILE);
        if (!is_array($parsed)) {
            self::$operations = [];

            return self::$operations;
        }

        $ops = $parsed['operations'] ?? [];
        self::$operations = is_array($ops) ? $ops : [];

        return self::$operations;
    }

    public static function resetCacheForTests(): void
    {
        self::$operations = null;
    }
}
