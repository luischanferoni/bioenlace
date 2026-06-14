<?php

namespace common\components\Core\Permission;

/**
 * Clave de permiso RBAC de un intent: siempre {@see intent_id} (sin campo YAML `permission`).
 */
final class IntentPermissionResolver
{
    /**
     * @param array<string, mixed> $manifest
     */
    public static function resolve(string $intentId, array $manifest = []): string
    {
        return trim($intentId);
    }
}
