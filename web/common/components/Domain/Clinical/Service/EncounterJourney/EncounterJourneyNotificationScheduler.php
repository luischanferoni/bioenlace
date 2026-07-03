<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;

/**
 * Programa recordatorios del journey desde metadata de ventanas.
 */
final class EncounterJourneyNotificationScheduler
{
    private EncounterPhaseWindowsCatalogService $catalog;

    public function __construct(?EncounterPhaseWindowsCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new EncounterPhaseWindowsCatalogService();
    }

    public function scheduleForTurno(Turno $turno, int $turnoTimestamp): void
    {
        if ($turnoTimestamp <= time()) {
            return;
        }

        foreach ($this->catalog->phaseIds() as $phaseId) {
            foreach ($this->catalog->notifications($phaseId) as $notif) {
                $offsetSec = $this->catalog->offsetSeconds($notif['offset']);
                if ($offsetSec === null) {
                    continue;
                }
                $runAt = $turnoTimestamp + $offsetSec;
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
}
