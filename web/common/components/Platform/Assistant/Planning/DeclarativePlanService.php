<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Context\AssistantContextAnchorBag;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectResolver;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;

/**
 * Plan de herramientas HIS. Guide responde con lo que discovery adjuntó.
 */
final class DeclarativePlanService
{
    /**
     * @param list<string> $contextAreas
     * @param list<array{span: string, category: string, synonyms: list<string>}> $extractions
     */
    public static function plan(
        array $contextAreas,
        array $extractions,
        AssistantContextAnchorBag $anchors
    ): DeclarativePlanResult {
        $toolIds = [];
        $reasons = [];

        $loadPlan = AssistantContextAreaAspectResolver::plan(
            $contextAreas,
            $extractions,
            'guide',
            $anchors
        );
        foreach ($loadPlan->aspectKeys as $aspectKey) {
            $toolIds[] = self::aspectToolId($aspectKey);
        }
        if ($loadPlan->aspectKeys !== []) {
            $reasons[] = 'areas:' . implode(',', $contextAreas);
        }

        return new DeclarativePlanResult(
            array_values(array_unique($toolIds)),
            implode('; ', $reasons),
        );
    }

    public static function aspectToolId(string $aspectKey): string
    {
        return 'aspect:' . AssistantContextHISAreaAspect::aspectKey($aspectKey);
    }
}
