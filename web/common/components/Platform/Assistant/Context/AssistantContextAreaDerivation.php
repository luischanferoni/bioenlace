<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Assistant\Catalog\SmartCatalogEntry;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;

/**
 * Áreas activas del turno = dominios de los intents del match (carpeta bajo metadata).
 */
final class AssistantContextAreaDerivation
{
    /**
     * @return list<string>
     */
    public static function fromMatch(?SmartCatalogMatchResult $match): array
    {
        if ($match === null || $match->best === null) {
            return [];
        }

        return self::fromEntry($match->best);
    }

    /**
     * @return list<string>
     */
    public static function fromEntry(SmartCatalogEntry $entry): array
    {
        $intentIds = [];
        if ($entry->toolType === 'intent' && trim($entry->toolRef) !== '') {
            $intentIds[] = trim($entry->toolRef);
        }
        foreach ($entry->ctaIntentIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $intentIds[] = $id;
            }
        }

        $areas = [];
        foreach ($intentIds as $intentId) {
            $domain = IntentSchemaPaths::domainForIntentId($intentId);
            if ($domain === null || $domain === '') {
                continue;
            }
            if (!AssistantContextHISArea::isValid($domain) || AssistantContextHISArea::isContextOnly($domain)) {
                continue;
            }
            $areas[] = $domain;
        }

        // Entradas aspect/article sin intent: el trigger de área del catálogo aporta el dominio.
        foreach ($entry->triggerContextAreas as $area) {
            $area = strtolower(trim((string) $area));
            if ($area === '' || !AssistantContextHISArea::isValid($area)) {
                continue;
            }
            if (AssistantContextHISArea::isContextOnly($area)) {
                continue;
            }
            $areas[] = $area;
        }

        return AssistantContextHISArea::sortByProductPriority($areas);
    }
}
