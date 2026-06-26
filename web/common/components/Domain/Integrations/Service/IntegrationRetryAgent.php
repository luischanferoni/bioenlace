<?php

namespace common\components\Domain\Integrations\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use Yii;

/**
 * Agente E02 v1: auditoría y alertas de reintentos / dead-letter en integraciones.
 */
final class IntegrationRetryAgent
{
    public const AGENT_ID = 'integration-retry';

    public const TRIGGER_TYPE = 'integration_job';

    public function onJobFailed(ClinicalHistoryOutboundJob $job, string $message, bool $retryable): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            $retryable ? 'requeue_scheduled' : 'failed_non_retryable',
            (int) $job->id,
            (int) $job->encounter_id,
            null,
            (string) ($job->connector_key ?? 'clinical_history_exchange'),
            [
                'intentos' => (int) $job->intentos,
                'estado' => (string) $job->estado,
                'run_at' => (string) $job->run_at,
            ],
            ['message' => $message, 'retryable' => $retryable]
        );
    }

    public function onJobDead(ClinicalHistoryOutboundJob $job, string $message): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $connectorKey = (string) ($job->connector_key ?? 'clinical_history_exchange');
        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'dead_letter',
            (int) $job->id,
            (int) $job->encounter_id,
            null,
            $connectorKey,
            [
                'intentos' => (int) $job->intentos,
                'ultimo_error' => $message,
            ],
            ['estado' => ClinicalHistoryOutboundJob::ESTADO_MUERTO]
        );

        $this->maybeNotifyOps($connectorKey, (int) $job->id, $message);
    }

    private function maybeNotifyOps(string $connectorKey, int $jobId, string $message): void
    {
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return;
        }

        $connectors = is_array($config['connectors'] ?? null) ? $config['connectors'] : [];
        $connCfg = is_array($connectors[$connectorKey] ?? null) ? $connectors[$connectorKey] : [];
        if (empty($connCfg['dead_letter_notify'])) {
            return;
        }

        $opsCfg = Yii::$app->params['integrationRetry'] ?? [];
        $personaIds = is_array($opsCfg['ops_persona_ids'] ?? null) ? $opsCfg['ops_persona_ids'] : [];
        if ($personaIds === []) {
            Yii::warning(
                'IntegrationRetryAgent dead-letter job=' . $jobId . ' connector=' . $connectorKey . ': ' . $message,
                'integration-retry'
            );

            return;
        }

        $alert = is_array($config['ops_alert'] ?? null) ? $config['ops_alert'] : [];
        $replace = [
            '{{connector}}' => $connectorKey,
            '{{job_id}}' => (string) $jobId,
            '{{message}}' => mb_substr($message, 0, 200),
        ];
        $title = str_replace(array_keys($replace), array_values($replace), (string) ($alert['title'] ?? 'Integración fallida'));
        $body = str_replace(array_keys($replace), array_values($replace), (string) ($alert['body_template'] ?? ''));

        $push = new PushNotificationSender();
        foreach ($personaIds as $idPersona) {
            $id = (int) $idPersona;
            if ($id <= 0) {
                continue;
            }
            $push->sendToPersona(
                $id,
                [
                    'type' => PushNotificationTypes::INTEGRATION_DEAD_LETTER_OPS,
                    'job_id' => (string) $jobId,
                    'connector' => $connectorKey,
                ],
                $title,
                $body,
                true
            );
        }
    }

    private function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['autonomous_agent_integration_retry_enabled'] ?? true);
    }
}
