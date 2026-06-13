<?php

namespace common\components\Core\Permission\Domain;

/**
 * Política de dominio: autoriza una operación sobre un recurso concreto (ABAC / ownership).
 */
interface DomainOperationPolicyInterface
{
    /**
     * @param object|array<string, mixed>|null $resource turno, params de alta, PES, etc.
     *
     * @throws DomainOperationForbiddenException
     * @throws \InvalidArgumentException recurso incompatible
     */
    public function assert(DomainOperationContext $ctx, $resource): void;
}
