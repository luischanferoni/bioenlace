<?php

namespace common\components\Domain\Person\Assistant;

use common\components\Domain\Person\Service\PersonaBusquedaAsistenteUiService;
use common\components\Platform\Assistant\Service\HintCandidateMapper;
use common\components\Platform\Assistant\Service\HintCandidateProviderInterface;
use common\components\Platform\Assistant\Service\HintResolutionContext;

/**
 * Candidatos de hints para búsqueda de personas (staff).
 */
final class PersonHintCandidateProvider implements HintCandidateProviderInterface
{
    public static function providerKey(): string
    {
        return 'person';
    }

    public static function providesFor(string $entity, HintResolutionContext $ctx): bool
    {
        return strtolower(trim($entity)) === 'persona';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function candidates(string $entity, HintResolutionContext $ctx, ?string $searchQuery): array
    {
        if (!self::providesFor($entity, $ctx)) {
            return [];
        }

        $q = $searchQuery !== null ? trim($searchQuery) : '';
        if ($q === '') {
            return [];
        }

        return HintCandidateMapper::mapUiJsonItems(PersonaBusquedaAsistenteUiService::buscar($q));
    }
}
