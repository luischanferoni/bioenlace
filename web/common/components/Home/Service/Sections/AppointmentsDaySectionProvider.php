<?php

namespace common\components\Home\Service\Sections;

use common\components\Home\Service\StaffClinicalDayListService;

final class AppointmentsDaySectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $fecha = (string) ($context['fecha'] ?? date('Y-m-d'));
        $conPrueba = !empty($context['prueba']);
        $payload = (new StaffClinicalDayListService())->turnosAmbulatorioMedico($fecha, null, $conPrueba);

        return [
            'items' => $payload['turnos'],
            'total' => $payload['total'],
            'fecha' => $payload['fecha'],
        ];
    }
}
