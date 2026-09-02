<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Aspectos por área HIS desde metadata ({@see area-aspects.yaml}).
 */
final class AssistantContextAreaAspectCatalog
{
    /** @var array<string, list<string>>|null */
    private static ?array $aspectsByAreaCache = null;

    public static function resetCacheForTests(): void
    {
        self::$aspectsByAreaCache = null;
    }

    /**
     * @param list<array{span: string, category: string, synonyms: list<string>}> $extractions
     * @return list<string>
     */
    public static function aspectsForArea(string $areaId, array $extractions): array
    {
        $areaId = trim($areaId);
        if ($areaId === '' || !AssistantContextHISArea::isValid($areaId)) {
            return [];
        }

        $aspects = self::aspectsByArea()[$areaId] ?? [];
        if ($areaId === AssistantContextHISArea::APPOINTMENTS && self::wantsAppointmentHistory($extractions)) {
            $aspects[] = AssistantContextHISAreaAspect::APPOINTMENT_HISTORY_SUBJECT_AT_SITE;
        }

        $valid = [];
        foreach ($aspects as $aspect) {
            if (AssistantContextHISAreaAspect::isValid($aspect)) {
                $valid[] = $aspect;
            }
        }

        return array_values(array_unique($valid));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function aspectsByArea(): array
    {
        if (self::$aspectsByAreaCache !== null) {
            return self::$aspectsByAreaCache;
        }

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::areaAspectsCatalogFile());
        $raw = $config['areas'] ?? [];
        if (!is_array($raw)) {
            return self::$aspectsByAreaCache = [];
        }

        $out = [];
        foreach ($raw as $areaId => $row) {
            if (!is_string($areaId) || !is_array($row)) {
                continue;
            }
            $areaId = trim($areaId);
            if ($areaId === '') {
                continue;
            }
            $list = $row['aspects'] ?? [];
            if (!is_array($list)) {
                continue;
            }
            $aspects = [];
            foreach ($list as $aspect) {
                if (is_string($aspect) && trim($aspect) !== '') {
                    $aspects[] = trim($aspect);
                }
            }
            $out[$areaId] = $aspects;
        }

        return self::$aspectsByAreaCache = $out;
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
}
