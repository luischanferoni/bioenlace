<?php

namespace common\components\Core\Permission\Domain;

/**
 * Evalúa políticas de dominio declaradas para una clave de operación del catálogo RBAC.
 */
final class DomainOperationAuthorizer
{
    public function __construct(
        private ?DomainOperationPolicyCatalog $catalog = null
    ) {
        $this->catalog = $catalog ?? new DomainOperationPolicyCatalog();
    }

    /**
     * @param object|array<string, mixed>|null $resource
     *
     * @throws DomainOperationForbiddenException
     */
    public function assert(string $operationKey, $resource = null, ?DomainOperationContext $ctx = null): void
    {
        $operationKey = trim($operationKey);
        if ($operationKey === '') {
            return;
        }

        $def = $this->catalog->getOperationDefinition($operationKey);
        if ($def === null || $def === []) {
            return;
        }

        $ctx = $ctx ?? DomainOperationContext::fromApplication(
            is_array($resource) ? $resource : []
        );

        if ($ctx->isSuperadmin) {
            return;
        }

        $anyOf = $this->normalizeHandlerList($def['any_of'] ?? null);
        if ($anyOf !== []) {
            $this->assertAnyOf($anyOf, $ctx, $resource);

            return;
        }

        $allOf = $this->normalizeHandlerList($def['policies'] ?? null);
        if ($allOf === []) {
            return;
        }

        foreach ($allOf as $handlerId) {
            $this->runPolicy($handlerId, $ctx, $resource);
        }
    }

    /**
     * @param list<string> $handlerIds
     * @param object|array<string, mixed>|null $resource
     */
    private function assertAnyOf(array $handlerIds, DomainOperationContext $ctx, $resource): void
    {
        $lastForbidden = null;
        foreach ($handlerIds as $handlerId) {
            try {
                $this->runPolicy($handlerId, $ctx, $resource);

                return;
            } catch (DomainOperationForbiddenException $e) {
                $lastForbidden = $e;
            }
        }

        throw $lastForbidden ?? new DomainOperationForbiddenException('No autorizado.');
    }

    /**
     * @param object|array<string, mixed>|null $resource
     */
    private function runPolicy(string $handlerId, DomainOperationContext $ctx, $resource): void
    {
        DomainOperationPolicyRegistry::get($handlerId)->assert($ctx, $resource);
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeHandlerList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $id = trim((string) $item);
            if ($id !== '') {
                $out[] = $id;
            }
        }

        return $out;
    }
}
