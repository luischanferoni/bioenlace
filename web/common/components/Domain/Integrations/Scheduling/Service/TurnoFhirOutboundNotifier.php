<?php

namespace common\components\Domain\Integrations\Scheduling\Service;

use common\models\Scheduling\Turno;
use Yii;

/**
 * Punto único para notificar cambios de estado hacia FHIR externo (fail-soft).
 */
final class TurnoFhirOutboundNotifier
{
    public static function afterEstadoChanged(Turno $turno): void
    {
        try {
            (new FhirAppointmentOutboundSyncService())->syncFromTurno($turno);
        } catch (\Throwable $e) {
            Yii::warning([
                'id_turnos' => (int) $turno->id_turnos,
                'external_appointment_id' => $turno->external_appointment_id,
                'error' => $e->getMessage(),
            ], 'fhir-scheduling-outbound');
        }
    }

    public static function afterEstadoChangedById(int $idTurnos): void
    {
        $turno = Turno::findOne($idTurnos);
        if ($turno !== null) {
            self::afterEstadoChanged($turno);
        }
    }
}
