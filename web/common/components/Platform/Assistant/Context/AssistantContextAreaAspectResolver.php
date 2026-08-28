<?php

namespace common\components\Platform\Assistant\Context;

use Yii;

final class AssistantContextAreaAspectResolver
{
    /**
     * @param list<string> $areaIds
     * @param list<array{span: string, category: string, synonyms: list<string>}> $extractions
     */
    public static function plan(
        array $areaIds,
        array $extractions,
        string $channel,
        AssistantContextAnchorBag $anchors
    ): AssistantContextLoadPlan {
        $aspectKeys = [];
        foreach ($areaIds as $areaId) {
            if (!AssistantContextHISArea::isValid($areaId)) {
                continue;
            }
            foreach (self::aspectsForArea($areaId, $extractions) as $aspect) {
                $aspectKeys[] = $aspect;
            }
        }

        $aspectKeys = self::dedupeSortByPriority($aspectKeys);
        $max = max(1, (int) (Yii::$app->params['asistente_context_max_aspects'] ?? 6));
        if (count($aspectKeys) > $max) {
            $aspectKeys = array_slice($aspectKeys, 0, $max);
        }

        return new AssistantContextLoadPlan($aspectKeys, $anchors);
    }

    /**
     * @param list<array{span: string, category: string, synonyms: list<string>}> $extractions
     * @return list<string>
     */
    private static function aspectsForArea(string $areaId, array $extractions): array
    {
        if ($areaId !== AssistantContextHISArea::APPOINTMENTS) {
            return [];
        }

        $aspects = [
            AssistantContextHISAreaAspect::APPOINTMENT_CURRENT,
            AssistantContextHISAreaAspect::SITE_APPOINTMENT_POLICIES,
            AssistantContextHISAreaAspect::APPOINTMENT_SCHEDULING_SETUP,
        ];

        if (self::wantsAppointmentHistory($extractions)) {
            $aspects[] = AssistantContextHISAreaAspect::APPOINTMENT_HISTORY_SUBJECT_AT_SITE;
        }

        return $aspects;
    }

    /**
     * @param list<array{span: string, category: string, synonyms: list<string>}> $extractions
     */
    private static function wantsAppointmentHistory(array $extractions): bool
    {
        foreach ($extractions as $ex) {
            $span = mb_strtolower(trim((string) ($ex['span'] ?? '')), 'UTF-8');
            if ($span === '') {
                continue;
            }
            if (
                str_contains($span, 'última')
                || str_contains($span, 'ultima')
                || str_contains($span, 'cuántas')
                || str_contains($span, 'cuantas')
                || str_contains($span, 'historial')
                || str_contains($span, 'veces')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $aspectKeys
     * @return list<string>
     */
    private static function dedupeSortByPriority(array $aspectKeys): array
    {
        $unique = array_values(array_unique($aspectKeys));
        usort($unique, static function (string $a, string $b): int {
            return AssistantContextHISAreaAspect::priority($a) <=> AssistantContextHISAreaAspect::priority($b);
        });

        return $unique;
    }
}
