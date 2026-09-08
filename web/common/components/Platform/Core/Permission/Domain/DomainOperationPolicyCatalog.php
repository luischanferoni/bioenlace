<?php

namespace common\components\Platform\Core\Permission\Domain;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo declarativo: operación RBAC → políticas de recurso (YAML).
 * Operaciones solo ABAC (sin permiso assignable) viven en {@see DOMAIN_ONLY_OPERATIONS}.
 */
final class DomainOperationPolicyCatalog
{
    /**
     * Operaciones solo ABAC (sin permiso assignable en auth_item; RBAC vía ruta/intent padre).
     *
     * @var list<string>
     */
    public const DOMAIN_ONLY_OPERATIONS = [
        'Encounter.access',
        'Clinical.staff_efector',
        'Internacion.staff_access',
        'ProfesionalEfectorServicio.flow_closure_staff',
        'ProfesionalEfectorServicio.flow_closure_own',
        'ProfesionalEfectorServicio.condicion_laboral_staff',
        'ProfesionalEfectorServicio.condicion_laboral_own',
        'ProfesionalEfectorServicio.pes_own',
    ];

    private static function configFile(): string
    {
        return ProductMetadataPaths::domainOperationPoliciesFile();
    }

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $operations = null;

    public static function isDomainOnlyOperation(string $op): bool
    {
        $op = trim($op);

        return $op !== '' && in_array($op, self::DOMAIN_ONLY_OPERATIONS, true);
    }

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

        $configFile = self::configFile();
        if (!is_file($configFile)) {
            self::$operations = [];

            return self::$operations;
        }

        $parsed = Yaml::parseFile($configFile);
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
