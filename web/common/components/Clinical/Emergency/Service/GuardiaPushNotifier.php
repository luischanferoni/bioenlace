<?php

namespace common\components\Clinical\Emergency\Service;

use common\components\Clinical\Emergency\Enum\TriageScale;
use common\components\Core\Service\Push\PushNotificationSender;
use common\components\Core\Service\Push\PushNotificationTypes;
use common\models\Guardia;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;

/**
 * Notificaciones push del circuito de guardia (staff).
 */
final class GuardiaPushNotifier
{
    public function notifyAssigned(Guardia $guardia, int $idPes): void
    {
        $idPersona = $this->personaIdFromPes($idPes);
        if ($idPersona <= 0) {
            return;
        }
        $nombre = $guardia->paciente
            ? $guardia->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';

        (new PushNotificationSender())->sendToPersona(
            $idPersona,
            [
                'type' => PushNotificationTypes::EMERGENCY_ASSIGNED_TO_YOU,
                'guardia_id' => (string) $guardia->id,
                'id_persona' => (string) $guardia->id_persona,
            ],
            'Guardia: paciente asignado',
            'Te asignaron la atención de ' . $nombre . ' en guardia.',
            true
        );
    }

    /**
     * Niveles Manchester 1–2: aviso al asignado y al triager (si distintos).
     */
    public function notifyCriticalTriage(Guardia $guardia, int $level, ?int $triagerPesId = null): void
    {
        if ($level > 2) {
            return;
        }
        $nombre = $guardia->paciente
            ? $guardia->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';
        $meta = TriageScale::levelMeta()[$level] ?? ['label' => 'Urgente'];
        $title = $level === 1 ? 'Guardia: prioridad inmediata' : 'Guardia: muy urgente';
        $body = $nombre . ' — ' . ($meta['label'] ?? (string) $level);

        $sent = [];
        foreach ([(int) $guardia->id_profesional_efector_servicio, (int) ($triagerPesId ?? 0)] as $pesId) {
            if ($pesId <= 0 || isset($sent[$pesId])) {
                continue;
            }
            $idPersona = $this->personaIdFromPes($pesId);
            if ($idPersona <= 0) {
                continue;
            }
            (new PushNotificationSender())->sendToPersona(
                $idPersona,
                [
                    'type' => PushNotificationTypes::EMERGENCY_PATIENT_CRITICAL,
                    'guardia_id' => (string) $guardia->id,
                    'level' => (string) $level,
                ],
                $title,
                $body,
                true
            );
            $sent[$pesId] = true;
        }
    }

    private function personaIdFromPes(int $idPes): int
    {
        $pes = ProfesionalEfectorServicio::findOne($idPes);

        return $pes ? (int) $pes->id_persona : 0;
    }
}
