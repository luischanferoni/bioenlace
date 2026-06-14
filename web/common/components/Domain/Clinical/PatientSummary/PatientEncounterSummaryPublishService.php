<?php

namespace common\components\Domain\Clinical\PatientSummary;

use common\components\Domain\Clinical\CareCohort\Service\CareFollowupSchedulerService;
use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationNotifyRecipientService;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterPatientSummary;
use common\models\Clinical\EncounterPatientSummaryPublishQueue;
use Yii;

/**
 * Programa y ejecuta la publicación del resumen paciente (T+Δ tras finalizar).
 */
final class PatientEncounterSummaryPublishService
{
    public const DELAY_MINUTES = 3;

    private PatientEncounterSummaryBuilder $builder;

    public function __construct(?PatientEncounterSummaryBuilder $builder = null)
    {
        $this->builder = $builder ?? new PatientEncounterSummaryBuilder();
    }

    public function schedulePublication(Encounter $encounter): void
    {
        if ($encounter->encounter_class !== Encounter::ENCOUNTER_CLASS_AMB) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $runAt = date('Y-m-d H:i:s', time() + self::DELAY_MINUTES * 60);

        $row = EncounterPatientSummaryPublishQueue::findOne(['encounter_id' => (int) $encounter->id])
            ?? new EncounterPatientSummaryPublishQueue();

        if ($row->estado === EncounterPatientSummaryPublishQueue::ESTADO_ENVIADA) {
            $row->estado = EncounterPatientSummaryPublishQueue::ESTADO_PENDIENTE;
        }

        $row->encounter_id = (int) $encounter->id;
        $row->run_at = $runAt;
        $row->estado = EncounterPatientSummaryPublishQueue::ESTADO_PENDIENTE;
        $row->updated_at = $now;
        if ($row->isNewRecord) {
            $row->created_at = $now;
            $row->intentos = 0;
        }
        $row->save(false);
    }

    public function publishEncounter(int $encounterId, bool $sendPush = true): bool
    {
        $encounter = Encounter::findOne(['id' => $encounterId, 'deleted_at' => null]);
        if ($encounter === null) {
            return false;
        }
        if ($encounter->status !== EncounterStatus::FINISHED
            || $encounter->encounter_class !== Encounter::ENCOUNTER_CLASS_AMB) {
            $this->cancelQueueRow($encounterId);

            return false;
        }

        $dto = $this->builder->build($encounter);
        if ($dto === null) {
            $this->markQueueFailed($encounterId, 'Encounter no publicable');

            return false;
        }

        $now = date('Y-m-d H:i:s');
        $json = json_encode($dto, JSON_UNESCAPED_UNICODE);

        $summary = EncounterPatientSummary::findOne(['encounter_id' => $encounterId])
            ?? new EncounterPatientSummary();
        $summary->encounter_id = $encounterId;
        $summary->subject_persona_id = (int) $encounter->subject_persona_id;
        $summary->narrative_text = (string) ($dto['narrativeText'] ?? '');
        $summary->summary_json = $json !== false ? $json : null;
        $summary->published_at = $now;
        $summary->version = $summary->isNewRecord ? 1 : ((int) $summary->version + 1);
        $summary->updated_at = $now;
        if ($summary->isNewRecord) {
            $summary->created_at = $now;
        }
        $summary->save(false);

        $queue = EncounterPatientSummaryPublishQueue::findOne(['encounter_id' => $encounterId]);
        if ($queue !== null) {
            $queue->estado = EncounterPatientSummaryPublishQueue::ESTADO_ENVIADA;
            $queue->updated_at = $now;
            $queue->save(false);
        }

        if ($sendPush) {
            $this->sendPush((int) $encounter->subject_persona_id, $encounterId);
        }

        (new CareFollowupSchedulerService())->tryScheduleForEncounter($encounterId, $now);

        return true;
    }

    /**
     * @return int filas procesadas
     */
    public function processDueQueue(int $limit = 50): int
    {
        $rows = EncounterPatientSummaryPublishQueue::find()
            ->where(['estado' => EncounterPatientSummaryPublishQueue::ESTADO_PENDIENTE])
            ->andWhere(['<=', 'run_at', date('Y-m-d H:i:s')])
            ->orderBy(['run_at' => SORT_ASC])
            ->limit($limit)
            ->all();

        $n = 0;
        foreach ($rows as $row) {
            $row->intentos = (int) $row->intentos + 1;
            $row->save(false);
            try {
                if ($this->publishEncounter((int) $row->encounter_id, true)) {
                    $n++;
                }
            } catch (\Throwable $e) {
                $this->markQueueFailed((int) $row->encounter_id, $e->getMessage());
                Yii::error($e->getMessage(), 'encounter-patient-summary');
            }
        }

        return $n;
    }

    private function sendPush(int $subjectPersonaId, int $encounterId): void
    {
        if ($subjectPersonaId <= 0) {
            return;
        }

        $recipientSvc = new PersonRepresentationNotifyRecipientService();
        $recipients = $recipientSvc->resolvePushRecipientPersonaIds(
            $subjectPersonaId,
            RepresentationPermission::CLINICAL_HISTORIA_RESUMEN
        );
        if ($recipients === []) {
            return;
        }

        $subjectLabel = $recipientSvc->subjectDisplayLabel($subjectPersonaId);
        $sender = new PushNotificationSender();

        foreach ($recipients as $recipientId) {
            $body = $recipientId === $subjectPersonaId
                ? 'Consultá qué indicó el profesional y tus próximos pasos.'
                : 'El resumen de atención de ' . $subjectLabel . ' está listo.';

            $sender->sendToPersona(
                $recipientId,
                [
                    'type' => PushNotificationTypes::ENCOUNTER_SUMMARY_READY,
                    'encounter_id' => (string) $encounterId,
                    'subject_persona_id' => (string) $subjectPersonaId,
                ],
                'Resumen de atención disponible',
                $body,
                true
            );
        }
    }

    private function cancelQueueRow(int $encounterId): void
    {
        $queue = EncounterPatientSummaryPublishQueue::findOne(['encounter_id' => $encounterId]);
        if ($queue === null) {
            return;
        }
        $queue->estado = EncounterPatientSummaryPublishQueue::ESTADO_CANCELADA;
        $queue->updated_at = date('Y-m-d H:i:s');
        $queue->save(false);
    }

    private function markQueueFailed(int $encounterId, string $error): void
    {
        $queue = EncounterPatientSummaryPublishQueue::findOne(['encounter_id' => $encounterId]);
        if ($queue === null) {
            return;
        }
        $queue->estado = EncounterPatientSummaryPublishQueue::ESTADO_FALLIDA;
        $queue->ultimo_error = mb_substr($error, 0, 2000);
        $queue->updated_at = date('Y-m-d H:i:s');
        $queue->save(false);
    }
}
