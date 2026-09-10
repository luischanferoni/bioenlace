<?php

namespace common\components\Platform\Assistant\Service;

/**
 * Proveedor de candidatos para fuzzy de hints del asistente.
 *
 * Cada dominio declara qué entidades resuelve ({@see declaredEntities()});
 * {@see HintResolutionMetadata} y el catálogo de extracción se componen desde ahí.
 */
interface HintCandidateProviderInterface
{
    /**
     * Clave estable del provider (coincide con el dominio: scheduling, organization, person, …).
     */
    public static function providerKey(): string;

    /**
     * Entidades que este dominio puede resolver (source of truth de ids de extracción).
     *
     * @return list<string>
     */
    public static function declaredEntities(): array;

    public static function providesFor(string $entity, HintResolutionContext $ctx): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public static function candidates(string $entity, HintResolutionContext $ctx, ?string $searchQuery): array;
}
