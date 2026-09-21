<?php

namespace common\components\Domain\Person\Representation\Domain\Catalog;

use common\components\Domain\Person\Representation\Domain\RepresentationPermission;
use common\components\Domain\Person\Representation\Domain\Catalog\RepresentationPermissionsV1Catalog;
use common\models\Person\PersonDelegationConsent;
use common\models\Person\PersonRelated;

/**
 * Catálogo declarativo de permisos de representación ({@see RepresentationPermissionsV1Catalog}).
 */
final class RepresentationPermissionsCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return list<string>
     */
    public function getDefaultPermissions(): array
    {
        $defaults = self::load()['default_permissions'] ?? [];
        if (!is_array($defaults) || $defaults === []) {
            return RepresentationPermission::v1Defaults();
        }

        return array_values(array_filter(array_map('strval', $defaults)));
    }

    public function isKnownPermission(string $permission): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }
        $permissions = self::load()['permissions'] ?? [];

        return is_array($permissions) && array_key_exists($permission, $permissions);
    }

    public function linkGrantsPermission(PersonRelated $link, ?PersonDelegationConsent $consent, string $permission): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        $granted = $this->resolveGrantedPermissions($link, $consent);

        return in_array($permission, $granted, true);
    }

    /**
     * @return list<string>
     */
    public function resolveGrantedPermissions(PersonRelated $link, ?PersonDelegationConsent $consent): array
    {
        $fromLink = $link->getPermissionsList();
        if ($fromLink !== []) {
            return $fromLink;
        }
        if ($consent !== null) {
            $fromConsent = $consent->getProvisionPermissionsList();
            if ($fromConsent !== []) {
                return $fromConsent;
            }
        }

        return $this->getDefaultPermissions();
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $data = RepresentationPermissionsV1Catalog::config();
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo de permisos de representación inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
