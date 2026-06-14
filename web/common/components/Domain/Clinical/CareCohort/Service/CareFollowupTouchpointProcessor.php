<?php

namespace common\components\Domain\Clinical\CareCohort\Service;

use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationNotifyRecipientService;
use common\models\Clinical\CareFollowupTouchpointQueue;
use Yii;

/**
 * Envía push cuando un touchpoint de seguimiento está listo.
 */
final class CareFollowupTouchpointProcessor
{
    /**
     * @return int touchpoints notificados
     */
    public function processDue(int $limit = 50): int
    {
        if (!CarePackConfig::isEnabled()) {
            return 0;
        }

        $rows = CareFollowupTouchpointQueue::find()
            ->where(['estado' => CareFollowupTouchpointQueue::ESTADO_PENDIENTE])
            ->andWhere(['<=', 'run_at', date('Y-m-d H:i:s')])
            ->orderBy(['run_at' => SORT_ASC])
            ->limit($limit)
            ->all();

        $n = 0;
        foreach ($rows as $row) {
            $row->intentos = (int) $row->intentos + 1;
            $row->save(false);

            try {
                if ($this->notifyTouchpoint($row)) {
                    $n++;
                }
            } catch (\Throwable $e) {
                $this->markFailed($row, $e->getMessage());
                Yii::error($e->getMessage(), 'care-cohort-followup');
            }
        }

        return $n;
    }

    private function notifyTouchpoint(CareFollowupTouchpointQueue $row): bool
    {
        $subjectId = (int) $row->subject_persona_id;
        if ($subjectId <= 0) {
            $this->markFailed($row, 'subject_persona_id inválido');

            return false;
        }

        $recipientSvc = new PersonRepresentationNotifyRecipientService();
        $recipients = $recipientSvc->resolvePushRecipientPersonaIds(
            $subjectId,
            RepresentationPermission::CLINICAL_CARE_PACK_ASSISTANCE
        );
        if ($recipients === []) {
            $this->markFailed($row, 'sin destinatarios con cuenta para notificar');

            return false;
        }

        $title = trim((string) $row->title) ?: 'Seguimiento de tu atención';
        $subjectLabel = $recipientSvc->subjectDisplayLabel($subjectId);
        $sender = new PushNotificationSender();
        $sent = 0;

        foreach ($recipients as $recipientId) {
            $body = $recipientId === $subjectId
                ? 'Contanos cómo te sentís y revisá las recomendaciones de tu consulta.'
                : 'Seguimiento de la atención de ' . $subjectLabel
                    . ': completá el formulario de evolución.';

            $sender->sendToPersona(
                $recipientId,
                [
                    'type' => PushNotificationTypes::CARE_FOLLOWUP_TOUCHPOINT,
                    'encounter_id' => (string) (int) $row->encounter_id,
                    'touchpoint_id' => (string) (int) $row->id,
                    'touchpoint_key' => (string) $row->touchpoint_key,
                    'subject_persona_id' => (string) $subjectId,
                ],
                $title,
                $body,
                true
            );
            $sent++;
        }

        if ($sent === 0) {
            $this->markFailed($row, 'no se pudo enviar push a destinatarios');

            return false;
        }

        $now = date('Y-m-d H:i:s');
        $row->estado = CareFollowupTouchpointQueue::ESTADO_NOTIFICADA;
        $row->notified_at = $now;
        $row->updated_at = $now;
        $row->ultimo_error = null;
        $row->save(false);

        return true;
    }

    private function markFailed(CareFollowupTouchpointQueue $row, string $error): void
    {
        $row->ultimo_error = mb_substr($error, 0, 2000);
        $row->updated_at = date('Y-m-d H:i:s');
        if ((int) $row->intentos >= 5) {
            $row->estado = CareFollowupTouchpointQueue::ESTADO_FALLIDA;
        }
        $row->save(false);
    }
}
