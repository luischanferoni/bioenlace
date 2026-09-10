<?php

namespace common\components\Platform\Assistant\Service;

use common\components\Platform\Core\Product\ProductRegistryConfig;

/**
 * Registro de {@see HintCandidateProviderInterface}.
 *
 * La declaración de entidades ({@see HintCandidateProviderInterface::declaredEntities()})
 * es la source of truth de ids de extracción; el ownership se compone acá.
 */
final class HintCandidateProviderRegistry
{
    /** @var array<string, class-string<HintCandidateProviderInterface>>|null */
    private static ?array $byKey = null;

    /** @var array<string, list<string>>|null entity → provider keys */
    private static ?array $ownershipCache = null;

    /**
     * @return list<class-string<HintCandidateProviderInterface>>
     */
    public static function orderedProviderClasses(): array
    {
        return array_values(self::providersByKey());
    }

    /**
     * @return list<class-string<HintCandidateProviderInterface>>
     */
    public static function providerClassesForEntity(string $entity): array
    {
        $entity = strtolower(trim($entity));
        $keys = self::providerKeysDeclaringEntity($entity);
        if ($keys === []) {
            return self::orderedProviderClasses();
        }

        $byKey = self::providersByKey();
        $classes = [];
        foreach ($keys as $key) {
            if (isset($byKey[$key])) {
                $classes[] = $byKey[$key];
            }
        }

        return $classes !== [] ? $classes : self::orderedProviderClasses();
    }

    /**
     * @return list<string>
     */
    public static function providerKeysDeclaringEntity(string $entity): array
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return [];
        }

        return self::entityOwnership()[$entity] ?? [];
    }

    /**
     * Unión de entidades declaradas por todos los providers (orden estable).
     *
     * @return list<string>
     */
    public static function allDeclaredEntities(): array
    {
        $ids = array_keys(self::entityOwnership());
        sort($ids);

        return $ids;
    }

    /**
     * @return array<string, list<string>> entity → provider keys
     */
    public static function entityOwnership(): array
    {
        if (self::$ownershipCache !== null) {
            return self::$ownershipCache;
        }

        $out = [];
        foreach (self::providersByKey() as $key => $class) {
            foreach ($class::declaredEntities() as $entity) {
                $entity = strtolower(trim((string) $entity));
                if ($entity === '') {
                    continue;
                }
                if (!isset($out[$entity])) {
                    $out[$entity] = [];
                }
                if (!in_array($key, $out[$entity], true)) {
                    $out[$entity][] = $key;
                }
            }
        }
        ksort($out);
        self::$ownershipCache = $out;

        return self::$ownershipCache;
    }

    /**
     * @return array<string, class-string<HintCandidateProviderInterface>>
     */
    private static function providersByKey(): array
    {
        if (self::$byKey !== null) {
            return self::$byKey;
        }

        self::$byKey = [];
        foreach (ProductRegistryConfig::section('hintCandidateProviders') as $class) {
            if (!is_string($class) || $class === ''
                || !is_subclass_of($class, HintCandidateProviderInterface::class)) {
                continue;
            }
            $key = trim($class::providerKey());
            if ($key === '') {
                continue;
            }
            self::$byKey[$key] = $class;
        }

        return self::$byKey;
    }

    public static function resetForTests(): void
    {
        self::$byKey = null;
        self::$ownershipCache = null;
    }
}
