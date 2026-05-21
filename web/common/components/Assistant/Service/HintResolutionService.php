<?php

namespace common\components\Assistant\Service;

/**
 * Cruza extracciones del preprocess con candidatos por entidad y aplica fuzzy único.
 */
final class HintResolutionService
{
    /**
     * @param list<string> $terms span + sinónimos de la extracción
     * @return array{id: string, value: string}|null
     */
    public static function resolve(
        string $entity,
        string $matchProperty,
        array $terms,
        HintResolutionContext $ctx
    ): ?array {
        $entity = trim($entity);
        $matchProperty = trim($matchProperty) !== '' ? trim($matchProperty) : 'nombre';
        if ($entity === '') {
            return null;
        }

        $searchQuery = self::firstNonEmptyTerm($terms);
        $candidates = HintCandidateProvider::forEntity($entity, $ctx, $searchQuery);
        if ($candidates === []) {
            return null;
        }

        return HintEntityMatcher::match($terms, $candidates, $matchProperty);
    }

    /**
     * @param list<string> $terms
     */
    private static function firstNonEmptyTerm(array $terms): ?string
    {
        foreach ($terms as $t) {
            $t = is_string($t) ? trim($t) : '';
            if ($t !== '') {
                return $t;
            }
        }

        return null;
    }
}
