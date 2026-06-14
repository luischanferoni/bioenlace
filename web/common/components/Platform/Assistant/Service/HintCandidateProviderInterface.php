<?php

namespace common\components\Platform\Assistant\Service;

/**
 * Proveedor de candidatos para fuzzy de hints del asistente.
 */
interface HintCandidateProviderInterface
{
    /**
     * Clave estable del provider (debe coincidir con entity_ownership en hint-resolution.yaml).
     */
    public static function providerKey(): string;

    public static function providesFor(string $entity, HintResolutionContext $ctx): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public static function candidates(string $entity, HintResolutionContext $ctx, ?string $searchQuery): array;
}
