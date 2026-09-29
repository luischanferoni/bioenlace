<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;

/**
 * Áreas activas = dominios de los intents del discovery.
 */
final class AssistantContextAreaDerivation
{
    /**
     * @param list<string> $intentIds
     * @return list<string>
     */
    public static function fromIntentIds(array $intentIds): array
    {
        $areas = [];
        foreach ($intentIds as $intentId) {
            if (!is_string($intentId)) {
                continue;
            }
            $intentId = trim($intentId);
            if ($intentId === '') {
                continue;
            }
            $domain = IntentSchemaPaths::domainForIntentId($intentId);
            if ($domain === null || $domain === '') {
                continue;
            }
            if (!AssistantContextHISArea::isValid($domain) || AssistantContextHISArea::isContextOnly($domain)) {
                continue;
            }
            $areas[] = $domain;
        }

        return AssistantContextHISArea::sortByProductPriority($areas);
    }
}
