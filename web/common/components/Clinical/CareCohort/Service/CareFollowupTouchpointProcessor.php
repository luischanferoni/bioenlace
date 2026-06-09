<?php

namespace common\components\Clinical\CareCohort\Service;

use common\components\Core\Service\Push\PushNotificationSender;
use common\components\Core\Service\Push\PushNotificationTypes;
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
        $idPersona = (int) $row->subject_persona_id;
        if ($idPersona <= 0) {
            $this->markFailed($row, 'subject_persona_id inválido');

            return false;
        }

        $title = trim((string) $row->title) ?: 'Seguimiento de tu atención';
        $body = 'Contanos cómo te sentís y revisá las recomendaciones de tu consulta.';

        (new PushNotificationSender())->sendToPersona(
            $idPersona,
            [
                'type' => PushNotificationTypes::CARE_FOLLOWUP_TOUCHPOINT,
                'encounter_id' => (string) (int) $row->encounter_id,
                'touchpoint_id' => (string) (int) $row->id,
                'touchpoint_key' => (string) $row->touchpoint_key,
            ],
            $title,
            $body,
            true
        );

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
