<?php

namespace common\components\Platform\Assistant\Service;

use common\components\Platform\Core\Product\ProductRegistryConfig;

final class HintCandidateProviderRegistry
{
    /** @var array<string, class-string<HintCandidateProviderInterface>>|null */
    private static ?array $byKey = null;

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
        $keys = HintResolutionMetadata::providerKeysForEntity($entity);
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
    }
}
