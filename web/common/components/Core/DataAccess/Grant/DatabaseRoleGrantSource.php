<?php

namespace common\components\Core\DataAccess\Grant;

use common\models\DataAccess\DataAccessRoleGrant;

/**
 * Grants persistidos en BD ({@see DataAccessRoleGrant}).
 */
final class DatabaseRoleGrantSource implements RoleGrantSourceInterface
{
    /** @var array<string, array<string, array{operations: list<string>, scope_checker?: string|null}|null>> */
    private static $cacheByRoles = [];

    public function getGrant(string $roleName, string $entityGroupKey): ?array
    {
        $roleName = trim($roleName);
        $entityGroupKey = trim($entityGroupKey);
        if ($roleName === '' || $entityGroupKey === '') {
            return null;
        }

        $cacheKey = $roleName;
        if (!isset(self::$cacheByRoles[$cacheKey])) {
            self::$cacheByRoles[$cacheKey] = $this->loadRoleMap($roleName);
        }

        return self::$cacheByRoles[$cacheKey][$entityGroupKey] ?? null;
    }

    public static function clearCache(): void
    {
        self::$cacheByRoles = [];
    }

    /**
     * @return array<string, array{operations: list<string>, scope_checker?: string|null}>
     */
    private function loadRoleMap(string $roleName): array
    {
        $rows = DataAccessRoleGrant::find()
            ->where(['role_name' => $roleName, 'active' => 1])
            ->all();

        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof DataAccessRoleGrant) {
                continue;
            }
            $grant = $row->toGrantShape();
            if ($grant === null) {
                continue;
            }
            $out[trim((string) $row->entity_group_key)] = $grant;
        }

        return $out;
    }
}
