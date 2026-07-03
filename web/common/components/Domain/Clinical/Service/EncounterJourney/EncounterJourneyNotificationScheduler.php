<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;

/**
 * Programa recordatorios del journey desde metadata de ventanas.
 */
final class EncounterJourneyNotificationScheduler
{
    private EncounterPhaseWindowsCatalogService $catalog;
    private EncounterPhaseWindowResolver $resolver;
    private EncounterJourneyContextBuilder $contextBuilder;
    private EncounterJourneyService $journeyService;

    public function __construct(
        ?EncounterPhaseWindowsCatalogService $catalog = null,
        ?EncounterPhaseWindowResolver $resolver = null,
        ?EncounterJourneyContextBuilder $contextBuilder = null,
        ?EncounterJourneyService $journeyService = null
    ) {
        $this->catalog = $catalog ?? new EncounterPhaseWindowsCatalogService();
        $this->resolver = $resolver ?? new EncounterPhaseWindowResolver($this->catalog);
        $this->contextBuilder = $contextBuilder ?? new EncounterJourneyContextBuilder();
        $this->journeyService = $journeyService ?? new EncounterJourneyService();
    }

    public function scheduleForTurno(Turno $turno, int $turnoTimestamp): void
    {
        if ($turnoTimestamp <= time()) {
            return;
        }

        $context = $this->contextBuilder->fromTurno($turno);

        foreach ($this->catalog->phaseIdsByAnchor('turno_start') as $phaseId) {
            $this->schedulePhaseNotifications($turno, $phaseId, $turnoTimestamp, $context);
        }
    }

    public function scheduleForEncounter(Encounter $encounter): void
    {
        $turnoId = (int) ($encounter->appointment_id ?? 0);
        if ($turnoId <= 0) {
            return;
        }

        $turno = Turno::findActive()->andWhere(['id_turnos' => $turnoId])->one();
        if (!$turno instanceof Turno) {
            return;
        }

        $anchorRaw = trim((string) ($encounter->period_end ?? ''));
        if ($anchorRaw === '') {
            return;
        }
        $anchorTs = strtotime($anchorRaw);
        if ($anchorTs === false || $anchorTs <= 0) {
            return;
        }

        $context = $this->contextBuilder->fromTurno($turno, $encounter);
        $journey = $this->journeyService->buildForTurno($turno, $encounter);
        $post = $journey['phases'][EncounterPhaseWindowsCatalogService::PHASE_POST] ?? [];
        if (empty($post['applies'])) {
            return;
        }

        foreach ($this->catalog->phaseIdsByAnchor('encounter_finished') as $phaseId) {
            $this->schedulePhaseNotifications($turno, $phaseId, $anchorTs, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function schedulePhaseNotifications(
        Turno $turno,
        string $phaseId,
        int $anchorTimestamp,
        array $context
    ): void {
        foreach ($this->resolver->notifications($phaseId, $context) as $notif) {
            $offsetSec = $this->catalog->offsetSeconds($notif['offset']);
            if ($offsetSec === null) {
                continue;
            }
            $runAt = $anchorTimestamp + $offsetSec;
            if ($runAt <= time()) {
                continue;
            }
            $this->insertIfNew(
                $turno,
                $notif['tipo'],
                date('Y-m-d H:i:s', $runAt),
                [
                    'phase' => $phaseId,
                    'title' => $notif['title'],
                    'body' => $notif['body'],
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertIfNew(Turno $turno, string $tipo, string $runAt, array $payload): void
    {
        $exists = TurnoNotificacionProgramada::find()
            ->where([
                'id_turno' => (int) $turno->id_turnos,
                'tipo' => $tipo,
                'estado' => TurnoNotificacionProgramada::ESTADO_PENDIENTE,
            ])
            ->exists();
        if ($exists) {
            return;
        }

        $row = new TurnoNotificacionProgramada();
        $row->id_turno = (int) $turno->id_turnos;
        $row->tipo = $tipo;
        $row->run_at = $runAt;
        $row->estado = TurnoNotificacionProgramada::ESTADO_PENDIENTE;
        $row->payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $row->save(false);
    }

    /**
     * Omite envío si la fase post-consulta ya no tiene pendientes.
     */
    public function shouldSendJourneyNotification(Turno $turno, string $tipo, array $meta): bool
    {
        if (!in_array($tipo, TurnoNotificacionProgramada::journeyPostConsultaTipos(), true)) {
            return true;
        }

        $encounter = Encounter::findOne(['appointment_id' => (int) $turno->id_turnos]);
        if ($encounter === null) {
            return false;
        }

        $journey = $this->journeyService->buildForTurno($turno, $encounter);
        $post = $journey['phases'][EncounterPhaseWindowsCatalogService::PHASE_POST] ?? [];
        if (empty($post['applies']) || !empty($post['completed'])) {
            return false;
        }

        if ($tipo === TurnoNotificacionProgramada::TIPO_JOURNEY_POSTCONSULTA_DISPONIBLE) {
            return true;
        }

        $followup = is_array($post['followup'] ?? null) ? $post['followup'] : [];
        $actionable = (int) ($followup['actionable_count'] ?? 0);
        $open = (int) ($followup['open_count'] ?? 0);

        return $open > 0 || $actionable > 0;
    }
}
