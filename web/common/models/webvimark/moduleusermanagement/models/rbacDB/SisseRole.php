<?php

namespace common\models\webvimark\moduleusermanagement\models\rbacDB;

use common\components\Core\Permission\RbacRoleQueryService;
use common\models\rbac\AuthRole;

/**
 * @deprecated Usar {@see RbacRoleQueryService} y {@see AuthRole}.
 */
class SisseRole extends AuthRole
{
    /**
     * @return list<AuthRole>|array<string, string>
     */
    public static function getAvailableRoles($showAll = false, $asArray = false)
    {
        return RbacRoleQueryService::getAvailableRoles((bool) $showAll, (bool) $asArray);
    }
}
