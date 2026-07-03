<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use common\components\Domain\Clinical\CareCohort\Service\CarePackConfig;
use common\components\Domain\Clinical\CareCohort\Service\CarePackRepository;
use common\components\Domain\Clinical\Service\AppointmentReasonWindowService;
use common\models\Clinical\CareAssistanceResponse;
use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;

/**
 * Contexto declarativo para ventanas y elegibilidad del journey (turno + encounter).
 */
final class EncounterJourneyContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function fromTurno(Turno $turno, ?Encounter $encounter = null): array
    {
        if ($encounter === null) {
            $encounter = Encounter::findOne(['appointment_id' => (int) $turno->id_turnos]);
        }

        $encounterId = $encounter !== null ? (int) $encounter->id : 0;
        $repo = new CarePackRepository();
        $binding = $encounterId > 0 ? $repo->findEncounterBinding($encounterId) : null;
        $assistancePackId = $binding !== null ? (int) ($binding->assistance_pack_id ?? 0) : 0;
        $followupPackId = $binding !== null ? (int) ($binding->followup_pack_id ?? 0) : 0;

        $asistenciaCompletada = $encounterId > 0
            && CareAssistanceResponse::find()->where(['encounter_id' => $encounterId])->exists();

        $intakeCatalog = new EncounterMotivosIntakeCatalogService();
        $motivosIntakeHabilitado = $intakeCatalog->isEnabled();
        $motivosIntakeCompletado = $encounter !== null
            && trim((string) ($encounter->motivos_intake_json ?? '')) !== '';
        $motivosIntakeBloqueaChat = $motivosIntakeHabilitado && !$motivosIntakeCompletado;

        $tipoAtencion = trim((string) ($turno->tipo_atencion ?? Turno::TIPO_ATENCION_PRESENCIAL));
        if ($encounter !== null && $encounter->parent_type === Encounter::PARENT_SOLICITUD_ASYNC) {
            $tipoAtencion = 'async';
        }

        $finished = $encounter !== null
            && trim((string) ($encounter->period_end ?? '')) !== ''
            && in_array(trim((string) $encounter->status), ['finished', 'cancelled'], true);

        return [
            'turno_id' => (int) $turno->id_turnos,
            'id_efector' => (int) ($turno->id_efector ?? 0),
            'turno_estado' => trim((string) $turno->estado),
            'turno_fecha' => trim((string) $turno->fecha),
            'turno_hora' => trim((string) $turno->hora),
            'tipo_atencion' => $tipoAtencion,
            'id_servicio_asignado' => (int) ($turno->id_servicio_asignado ?? 0),
            'encounter_id' => $encounterId > 0 ? $encounterId : null,
            'encounter_class' => $encounter !== null ? trim((string) $encounter->encounter_class) : '',
            'encounter_status' => $encounter !== null ? trim((string) $encounter->status) : '',
            'encounter_parent_type' => $encounter !== null ? trim((string) ($encounter->parent_type ?? '')) : '',
            'encounter_finished' => $finished,
            'encounter_finished_at' => $finished ? trim((string) $encounter->period_end) : null,
            'reserva_triage_code' => trim((string) ($turno->reserva_triage_code ?? '')),
            'care_cohort_enabled' => CarePackConfig::isEnabled(),
            'sin_pack_assistance' => $assistancePackId <= 0,
            'sin_pack_followup' => $followupPackId <= 0,
            'asistencia_completada' => $asistenciaCompletada,
            'motivos_resumen_present' => $encounter !== null && trim((string) $encounter->reason_text) !== '',
            'motivos_intake_habilitado' => $motivosIntakeHabilitado,
            'motivos_intake_completado' => $motivosIntakeCompletado,
            'motivos_intake_bloquea_chat' => $motivosIntakeBloqueaChat,
            'turno_starts_at' => $encounter !== null ? AppointmentReasonWindowService::turnoStartsAt($encounter) : null,
        ];
    }
}
