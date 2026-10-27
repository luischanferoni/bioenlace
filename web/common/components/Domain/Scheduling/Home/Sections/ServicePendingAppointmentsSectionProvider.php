<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Domain\Scheduling\Home\Application\Service\StaffSchedulingDayListService;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

/**
 * Turnos pendientes del día del servicio en sesión (cola de enfermería y administración en AMB).
 *
 * A diferencia de `appointments_day`, no depende de la agenda del profesional.
 */
final class ServicePendingAppointmentsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $fecha = (string) ($context['fecha'] ?? date('Y-m-d'));
        $payload = (new StaffSchedulingDayListService())->turnosPendientesDelServicio($fecha);

        return [
            'items' => $payload['turnos'],
            'total' => $payload['total'],
            'fecha' => $payload['fecha'],
        ];
    }
}
