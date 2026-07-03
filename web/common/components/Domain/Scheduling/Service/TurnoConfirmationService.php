<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use common\models\EfectorTurnosConfig;
use common\models\TurnoEventoAudit;
use common\components\Domain\Clinical\Service\AppointmentReasonWindowService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterJourneyNotificationScheduler;

class TurnoConfirmationService
{
    /**
     * Programa recordatorio y pedido de confirmación según config del efector.
     */
    public function programarNotificaciones(Turno $turno)
    {
        $dt = strtotime($turno->fecha . ' ' . $turno->hora . ':00');
        if ($dt === false) {
            return;
        }

        $cfg = EfectorTurnosConfig::getOrCreateForEfector((int) $turno->id_efector);
        if ($cfg->confirmacion_requerida) {
            $runConfirm = $dt - 48 * 3600;
            if ($runConfirm > time()) {
                $this->insertProgramada($turno, TurnoNotificacionProgramada::TIPO_CONFIRM_REQUEST, date('Y-m-d H:i:s', $runConfirm));
            }
        }

        if ($cfg->recordatorios_habilitados) {
            $runRem = $dt - 24 * 3600;
            if ($runRem > time()) {
                $this->insertProgramada($turno, TurnoNotificacionProgramada::TIPO_REMINDER, date('Y-m-d H:i:s', $runRem));
            }
            $runTransport = $dt - 12 * 3600;
            if ($runTransport > time()) {
                $this->insertProgramada($turno, TurnoNotificacionProgramada::TIPO_TRANSPORT_HINT, date('Y-m-d H:i:s', $runTransport));
            }
        }

        $this->programarMotivosIaBatch($turno, $dt);

        try {
            (new EncounterJourneyNotificationScheduler())->scheduleForTurno($turno, $dt);
        } catch (\Throwable $e) {
            \Yii::warning('EncounterJourney notifications: ' . $e->getMessage(), 'encounter-journey');
        }

        try {
            (new TurnoAntinoshowScheduler())->scheduleForTurno($turno, $dt);
        } catch (\Throwable $e) {
            \Yii::warning('Antinoshow schedule: ' . $e->getMessage(), 'turno-antinoshow');
        }
    }

    /**
     * Una sola inferencia de motivos ~N min antes del turno (ver AppointmentReasonWindowService).
     */
    protected function programarMotivosIaBatch(Turno $turno, int $turnoTimestamp): void
    {
        $encounter = Encounter::findOne(['appointment_id' => (int) $turno->id_turnos]);
        if (!$encounter) {
            return;
        }

        $minutes = AppointmentReasonWindowService::minutesBeforeClose();
        $runAt = $turnoTimestamp - $minutes * 60;
        if ($runAt <= time()) {
            return;
        }

        $this->insertProgramada(
            $turno,
            TurnoNotificacionProgramada::TIPO_MOTIVOS_IA_BATCH,
            date('Y-m-d H:i:s', $runAt),
            ['encounter_id' => (int) $encounter->id]
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    protected function insertProgramada(Turno $turno, $tipo, $runAt, ?array $payload = null)
    {
        $n = new TurnoNotificacionProgramada();
        $n->id_turno = (int) $turno->id_turnos;
        $n->tipo = $tipo;
        $n->run_at = $runAt;
        $n->estado = TurnoNotificacionProgramada::ESTADO_PENDIENTE;
        if ($payload !== null) {
            $n->payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        $n->save(false);
    }

    /**
     * Marca asistencia confirmada (idempotente).
     */
    public function confirmarAsistencia(Turno $turno, $idUser = null)
    {
        if ($turno->confirmado_en) {
            return true;
        }
        $turno->confirmado = 'SI';
        $turno->confirmado_en = date('Y-m-d H:i:s');
        if (!$turno->save(false, ['confirmado', 'confirmado_en'])) {
            return false;
        }
        TurnoEventoAudit::registrar($turno->id_turnos, TurnoEventoAudit::TIPO_CONFIRMED, $idUser);
        TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);
        return true;
    }

    public function ensureConfirmacionToken(Turno $turno)
    {
        if (!empty($turno->confirmacion_token)) {
            return $turno->confirmacion_token;
        }
        $turno->confirmacion_token = bin2hex(random_bytes(16));
        $turno->save(false, ['confirmacion_token']);
        return $turno->confirmacion_token;
    }
}
