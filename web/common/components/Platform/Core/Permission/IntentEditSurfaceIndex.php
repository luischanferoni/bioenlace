<?php

namespace common\components\Platform\Core\Permission;

/**
 * Enlace declarativo intent_id ↔ edit_surface_id (superficies DataAccess migradas).
 */
final class IntentEditSurfaceIndex
{
    /** @var array<string, list<string>>|null surface_id => intent_ids */
    private static ?array $intentsBySurfaceId = null;

    /** @var array<string, string>|null intent_id => surface_id */
    private static ?array $surfaceByIntentId = null;

    public static function resetCache(): void
    {
        self::$intentsBySurfaceId = null;
        self::$surfaceByIntentId = null;
        IntentManifestIndex::resetCache();
    }

    /**
     * @return list<string>
     */
    public static function intentsForSurface(string $surfaceId): array
    {
        self::ensureBuilt();
        $surfaceId = trim($surfaceId);

        return self::$intentsBySurfaceId[$surfaceId] ?? [];
    }

    public static function surfaceForIntent(string $intentId): ?string
    {
        self::ensureBuilt();
        $intentId = trim($intentId);

        return self::$surfaceByIntentId[$intentId] ?? null;
    }

    public static function isSurfaceMigrated(string $surfaceId): bool
    {
        return self::intentsForSurface($surfaceId) !== [];
    }

    private static function ensureBuilt(): void
    {
        if (self::$intentsBySurfaceId !== null) {
            return;
        }

        self::$intentsBySurfaceId = [];
        self::$surfaceByIntentId = [];

        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            $surfaceId = trim((string) ($meta['edit_surface_id'] ?? ''));
            if ($surfaceId === '') {
                continue;
            }
            self::$surfaceByIntentId[$intentId] = $surfaceId;
            self::$intentsBySurfaceId[$surfaceId] ??= [];
            if (!in_array($intentId, self::$intentsBySurfaceId[$surfaceId], true)) {
                self::$intentsBySurfaceId[$surfaceId][] = $intentId;
            }
        }
    }
}
