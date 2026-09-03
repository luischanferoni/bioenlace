<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Aspectos de contexto HIS (volcado 2ª IA). Clave JSON = {@see aspectKey()}.
 *
 * Source of truth: {@see catalog/area-aspects.yaml}.
 */
final class AssistantContextHISAreaAspect
{
    public const APPOINTMENT_CURRENT = 'appointment.current';
    public const SITE_APPOINTMENT_POLICIES = 'site.appointment.policies';
    public const APPOINTMENT_SCHEDULING_SETUP = 'appointment.scheduling.setup';
    public const APPOINTMENT_HISTORY_SUBJECT_AT_SITE = 'appointment.history.subject_at_site';

    /** @var array<string, array{area: string, priority: int, implemented: bool}>|null */
    private static ?array $metaCache = null;

    public static function aspectKey(string $aspect): string
    {
        return $aspect;
    }

    public static function isValid(string $aspect): bool
    {
        self::loadCatalog();

        return isset(self::$metaCache[trim($aspect)]);
    }

    public static function isImplemented(string $aspect): bool
    {
        self::loadCatalog();

        return (self::$metaCache[$aspect]['implemented'] ?? false) === true;
    }

    public static function area(string $aspect): string
    {
        self::loadCatalog();

        return (string) (self::$metaCache[$aspect]['area'] ?? '');
    }

    public static function priority(string $aspect): int
    {
        self::loadCatalog();

        return (int) (self::$metaCache[$aspect]['priority'] ?? 100);
    }

    /**
     * @return list<string>
     */
    public static function allForArea(string $areaId): array
    {
        self::loadCatalog();
        $out = [];
        foreach (self::$metaCache ?? [] as $aspect => $meta) {
            if (($meta['area'] ?? '') === $areaId) {
                $out[] = $aspect;
            }
        }

        sort($out);

        return $out;
    }

    public static function resetCacheForTests(): void
    {
        self::$metaCache = null;
    }

    private static function loadCatalog(): void
    {
        if (self::$metaCache !== null) {
            return;
        }

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::areaAspectsCatalogFile());
        $raw = $config['aspects'] ?? [];
        if (!is_array($raw)) {
            self::$metaCache = [];

            return;
        }

        $out = [];
        foreach ($raw as $aspect => $row) {
            if (!is_string($aspect) || !is_array($row)) {
                continue;
            }
            $aspect = trim($aspect);
            if ($aspect === '') {
                continue;
            }
            $out[$aspect] = [
                'area' => trim((string) ($row['area'] ?? '')),
                'priority' => (int) ($row['priority'] ?? 100),
                'implemented' => (bool) ($row['implemented'] ?? false),
            ];
        }

        self::$metaCache = $out;
    }
}
