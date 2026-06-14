<?php

namespace common\components\Domain\Scheduling\Home\Sections;

use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Domain\Clinical\Home\StaffClinicalDayListService;

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
