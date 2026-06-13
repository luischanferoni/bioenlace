<?php

namespace common\components\Organization\Service\Authorization;

use common\components\Core\Permission\Domain\DomainOperationContext;
use common\components\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Core\Permission\Domain\DomainOperationPolicyInterface;
use common\models\ProfesionalEfectorServicio;

/**
 * El PES pertenece al profesional autenticado (id_persona de sesión).
 */
final class OrganizationPesOwnPolicy implements DomainOperationPolicyInterface
{
    public function assert(DomainOperationContext $ctx, $resource): void
    {
        if ($ctx->isSuperadmin) {
            return;
        }

        $pes = $this->resolvePes($ctx, $resource);
        if ($pes === null) {
            throw new DomainOperationForbiddenException('Asignación profesional no encontrada.');
        }

        if ($ctx->idPersona <= 0 || (int) $pes->id_persona !== $ctx->idPersona) {
            throw new DomainOperationForbiddenException('Solo podés operar sobre tus propias asignaciones.');
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
