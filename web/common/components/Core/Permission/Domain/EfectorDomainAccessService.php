<?php

namespace common\components\Core\Permission\Domain;

use common\components\Organization\Service\Efectores\OrganizationEfectorAccess;

/**
 * Resolución de id_efector vía políticas de dominio (API, home panel, servicios).
 */
final class EfectorDomainAccessService
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
