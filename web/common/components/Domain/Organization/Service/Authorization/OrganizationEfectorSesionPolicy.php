<?php

namespace common\components\Domain\Organization\Service\Authorization;

use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\components\Domain\Organization\Service\Efectores\OrganizationEfectorAccess;
use common\models\ProfesionalEfectorServicio;

/**
 * Staff: operación acotada al efector de sesión o al indicado en request.
 */
final class OrganizationEfectorSesionPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        $idEfector = $this->resolveEfectorId($ctx, $resource);
        try {
            OrganizationEfectorAccess::assertCanAccessEfector($idEfector);
        } catch (\InvalidArgumentException $e) {
            throw new DomainOperationForbiddenException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param object|array<string, mixed>|null $resource
     */
    private function resolveEfectorId(DomainOperationContext $ctx, $resource): int
    {
        if ($resource instanceof ProfesionalEfectorServicio) {
            return (int) $resource->id_efector;
        }

        $params = is_array($resource) ? $resource : $ctx->params;

        return OrganizationEfectorAccess::resolveIdEfector(
            isset($params['id_efector']) && (int) $params['id_efector'] > 0
                ? (int) $params['id_efector']
                : ($ctx->idEfector ?? null)
        );
    }
}
