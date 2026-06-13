<?php

namespace common\components\Organization\Service\Authorization;

use common\components\Core\Permission\Domain\DomainOperationContext;
use common\components\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\components\Organization\Service\Efectores\OrganizationEfectorAccess;
use common\models\ProfesionalEfectorServicio;

/**
 * PES existente y perteneciente al efector de sesión/request; usuario con acceso al efector.
 */
final class OrganizationPesEfectorPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        $pes = $this->resolvePes($ctx, $resource);
        if ($pes === null) {
            throw new DomainOperationForbiddenException('Asignación profesional no encontrada.');
        }

        $idEfector = OrganizationEfectorAccess::resolveIdEfector($ctx->idEfector);
        try {
            OrganizationEfectorAccess::assertCanAccessEfector($idEfector);
        } catch (\InvalidArgumentException $e) {
            throw new DomainOperationForbiddenException($e->getMessage(), 0, $e);
        }

        if ((int) $pes->id_efector !== $idEfector) {
            throw new DomainOperationForbiddenException('Asignación inválida para este efector.');
        }

        $idServicio = isset($ctx->params['id_servicio']) ? (int) $ctx->params['id_servicio'] : 0;
        if ($idServicio > 0 && (int) $pes->id_servicio !== $idServicio) {
            throw new DomainOperationForbiddenException('id_servicio no coincide con la asignación profesional.');
        }
    }

    /**
     * @param object|array<string, mixed>|null $resource
     */
    private function resolvePes(DomainOperationContext $ctx, $resource): ?ProfesionalEfectorServicio
    {
        if ($resource instanceof ProfesionalEfectorServicio) {
            return $resource->deleted_at === null ? $resource : null;
        }

        $params = is_array($resource) ? $resource : $ctx->params;
        $idPes = (int) ($params['id_profesional_efector_servicio'] ?? $params['id_pes'] ?? 0);
        if ($idPes <= 0) {
            return null;
        }

        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);

        return $pes instanceof ProfesionalEfectorServicio ? $pes : null;
    }
}
