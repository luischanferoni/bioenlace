<?php

namespace common\components\Organization\Service\Authorization;

use common\components\Core\Permission\Domain\DomainOperationAuthorizer;
use common\components\Core\Permission\Domain\DomainOperationContext;
use common\components\Core\Permission\Domain\DomainOperationForbiddenException;
use common\models\ProfesionalEfectorServicio;

/**
 * Autorización de dominio sobre PES (flujos asistente, condición laboral, draft hydrator).
 */
final class ProfesionalEfectorServicioDomainAuthorizationService
{
    /**
     * @param array<string, mixed> $params
     *
     * @throws DomainOperationForbiddenException
     */
    public function assertPesOperation(array $params, string $operationKey): ProfesionalEfectorServicio
    {
        (new DomainOperationAuthorizer())->assert($operationKey, $params, DomainOperationContext::fromApplication($params));

        $idPes = (int) ($params['id_profesional_efector_servicio'] ?? 0);
        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null) {
            throw new DomainOperationForbiddenException('Asignación profesional no encontrada.');
        }

        return $pes;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws DomainOperationForbiddenException
     */
    public function assertFlowClosure(array $params, bool $requireOwnPes): ProfesionalEfectorServicio
    {
        return $this->assertPesOperation(
            $params,
            $requireOwnPes
                ? 'ProfesionalEfectorServicio.flow_closure_own'
                : 'ProfesionalEfectorServicio.flow_closure_staff'
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws DomainOperationForbiddenException
     */
    public function assertCondicionLaboral(array $params, bool $requireOwnPes): ProfesionalEfectorServicio
    {
        return $this->assertPesOperation(
            $params,
            $requireOwnPes
                ? 'ProfesionalEfectorServicio.condicion_laboral_own'
                : 'ProfesionalEfectorServicio.condicion_laboral_staff'
        );
    }
}
