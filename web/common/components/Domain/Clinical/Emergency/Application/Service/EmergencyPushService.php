<?php

namespace common\components\Domain\Clinical\Emergency\Application\Service;

use common\components\Domain\Clinical\Emergency\Application\Catalog\EmergencyPushCopyCatalog;
use common\components\Domain\Clinical\Emergency\Domain\TriageScale;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Clinical\Emergency\EmergencyEpisode;
use common\models\Person\Persona;
use common\models\Organization\ProfesionalEfectorServicio;

/**
 * Notificaciones push del circuito de guardia (staff).
 */
final class EmergencyPushService
{
    private EmergencyPushCopyCatalog $copyCatalog;

    public function __construct(?EmergencyPushCopyCatalog $copyCatalog = null)
    {
        $this->copyCatalog = $copyCatalog ?? new EmergencyPushCopyCatalog();
    }

    public function notifyAssigned(EmergencyEpisode $guardia, int $idPes): void
    {
        $idPersona = $this->personaIdFromPes($idPes);
        if ($idPersona <= 0) {
            return;
        }
        $nombre = $guardia->paciente
            ? $guardia->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';

        $copy = $this->copyCatalog->assigned($nombre);
        (new PushNotificationSender())->sendToPersona(
            $idPersona,
            [
                'type' => PushNotificationTypes::EMERGENCY_ASSIGNED_TO_YOU,
                'guardia_id' => (string) $guardia->id,
                'id_persona' => (string) $guardia->id_persona,
            ],
            $copy['title'],
            $copy['body'],
            true
        );
    }

    /**
     * Niveles Manchester 1–2: aviso al asignado y al triager (si distintos).
     */
    public function notifyCriticalTriage(EmergencyEpisode $guardia, int $level, ?int $triagerPesId = null): void
    {
        if ($level > 2) {
            return;
        }
        $nombre = $guardia->paciente
            ? $guardia->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';
        $meta = TriageScale::levelMeta()[$level] ?? ['label' => 'Urgente'];
        $copy = $this->copyCatalog->criticalTriage(
            $level,
            $nombre,
            (string) ($meta['label'] ?? (string) $level)
        );

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
                $copy['title'],
                $copy['body'],
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
