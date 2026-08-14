<?php

namespace common\components\Platform\Core\Permission;

/**
 * Autorización por capability_id (UI nativa y operaciones sin intent conversacional).
 */
final class CapabilityAccessService
{
    public static function userCanExecuteCapability(int $userId, string $capabilityId): bool
    {
        $capabilityId = trim($capabilityId);
        if ($capabilityId === '' || $userId <= 0) {
            return false;
        }

        if (BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        if (BioenlaceAccessChecker::userCanPermissionKey($userId, $capabilityId)) {
            return true;
        }

        foreach (CapabilityManifestIndex::routesForCapability($capabilityId) as $route) {
            if (BioenlaceAccessChecker::userCanApiRoute($userId, $route)) {
                return true;
            }
        }

        return false;
    }
}
