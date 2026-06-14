<?php

namespace frontend\modules\api\v1\components;

use common\components\Core\Permission\ApiRoutePermissionResolver;

/**
 * @deprecated Usar {@see BioenlaceApiAccessControl}. Alias de compatibilidad.
 */
class ApiGhostAccessControl extends BioenlaceApiAccessControl
{
    /**
     * @return list<string>
     */
    public static function permissionRouteCandidates(string $route): array
    {
        return ApiRoutePermissionResolver::candidates($route);
    }
}
