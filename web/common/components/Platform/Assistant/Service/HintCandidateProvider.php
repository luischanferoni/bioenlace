<?php

namespace common\components\Platform\Assistant\Service;

/**
 * Universo de candidatos para fuzzy de hints, por entidad de dominio (+ draft/intent).
 *
 * Implementaciones registradas en {@see HintCandidateProviderRegistry} / product-registries.php.
 */
final class HintCandidateProvider
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forEntity(string $entity, HintResolutionContext $ctx, ?string $searchQuery = null): array
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return [];
        }

        foreach (HintCandidateProviderRegistry::providerClassesForEntity($entity) as $class) {
            if (!$class::providesFor($entity, $ctx)) {
                continue;
            }
            $candidates = $class::candidates($entity, $ctx, $searchQuery);
            if ($candidates !== []) {
                return $candidates;
            }
        }

        return [];
    }
}
