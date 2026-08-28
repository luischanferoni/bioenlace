<?php

namespace common\components\Domain\Scheduling\Assistant\Context;

use common\components\Platform\Assistant\Context\AssistantContextAspectLoaderInterface;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Context\AssistantContextLoadContext;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ServiciosEfector;

final class AppointmentSchedulingSetupAspectLoader implements AssistantContextAspectLoaderInterface
{
    public function aspectKey(): string
    {
        return AssistantContextHISAreaAspect::APPOINTMENT_SCHEDULING_SETUP;
    }

    public function load(AssistantContextLoadContext $ctx): array
    {
        $pesId = $ctx->anchors->pesId;
        $serviceId = $ctx->anchors->serviceId;
        $schedulingMode = null;
        $slotMinutes = null;

        if ($pesId > 0) {
            $agenda = ProfesionalEfectorServicioAgenda::find()
                ->where(['id_profesional_efector_servicio' => $pesId])
                ->andWhere(['deleted_at' => null])
                ->one();
            if ($agenda !== null) {
                $schedulingMode = self::normalizeSchedulingMode((string) $agenda->formas_atencion);
                if ($agenda->duracion_slot_minutos !== null) {
                    $slotMinutes = (int) $agenda->duracion_slot_minutos;
                }
            }
        }

        if ($serviceId > 0 && $schedulingMode === null) {
            $se = ServiciosEfector::findOne($serviceId);
            if ($se !== null) {
                $schedulingMode = self::normalizeSchedulingMode((string) $se->formas_atencion);
            }
        }

        return [
            'scope' => [
                'pes_id' => $pesId > 0 ? $pesId : null,
                'service_id' => $serviceId > 0 ? $serviceId : null,
            ],
            'appointment_scheduling_setup' => [
                'scheduling_mode' => $schedulingMode ?? 'fixed_time_slot',
                'slot_duration_minutes' => $slotMinutes,
            ],
        ];
    }

    private static function normalizeSchedulingMode(string $formas): string
    {
        $f = strtoupper(trim($formas));
        if (
            $f === ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS
            || $f === 'ORDEN_LLEGADA'
        ) {
            return 'order_of_arrival';
        }

        return 'fixed_time_slot';
    }
}
