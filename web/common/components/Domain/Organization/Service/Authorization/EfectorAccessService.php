<?php

namespace common\components\Domain\Organization\Service\Authorization;

use common\components\Platform\Core\Permission\Domain\DomainOperationAuthorizer;
use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Domain\Organization\Service\Efectores\OrganizationEfectorAccess;

/**
 * Resolución de id_efector vía políticas de dominio (API, home panel, servicios).
 */
final class EfectorAccessService
{
    /**
     * @param array<string, mixed> $params
     *
     * @throws DomainOperationForbiddenException
     */
    public static function assertAndResolveIdEfector(string $operationKey, array $params = []): int
    {
        (new DomainOperationAuthorizer())->assert(
            $operationKey,
            $params,
            DomainOperationContext::fromApplication($params)
        );

        $from = isset($params['id_efector']) && (int) $params['id_efector'] > 0
            ? (int) $params['id_efector']
            : null;

        return OrganizationEfectorAccess::resolveIdEfector($from);
    }
}
