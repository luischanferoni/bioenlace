<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Agent\AutonomousAgentRuleEngine;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use Yii;

/**
 * Agente A04 v1: anti no-show por score de riesgo y checkpoints T−48h / T−2h.
 */
final class TurnoAntinoshowAgent
{
    public const AGENT_ID = 'turno-antinoshow';

    public const TRIGGER_CHECKPOINT = 'turno_antinoshow_checkpoint';

    public const TRIGGER_RELEASE = 'turno_antinoshow_release';

    private TurnoAntinoshowRiskService $risk;

    private TurnoAntinoshowScheduler $scheduler;

    public function __construct(
        ?TurnoAntinoshowRiskService $risk = null,
        ?TurnoAntinoshowScheduler $scheduler = null
    ) {
        $this->risk = $risk ?? new TurnoAntinoshowRiskService();
        $this->scheduler = $scheduler ?? new TurnoAntinoshowScheduler();
    }

    public function processCheckpoint(TurnoNotificacionProgramada $row, Turno $turno): string
    {
        if (!(Yii::$app->params['autonomous_agent_antinoshow_enabled'] ?? true)) {
            return 'cancelled';
        }

        if ($this->isAlreadyConfirmed($turno)) {
            return 'cancelled';
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return 'cancelled';
        }

        $meta = $row->payload_json ? json_decode($row->payload_json, true) : [];
        $hoursBefore = is_array($meta) ? (int) ($meta['hours_before'] ?? 0) : 0;
        $checkpointRules = $this->rulesForCheckpoint($config, $hoursBefore);
        if ($checkpointRules === []) {
            return 'cancelled';
        }

        $facts = $this->risk->assess($turno);
        if (!empty($facts['confirmed'])) {
            return 'cancelled';
        }

        $matched = AutonomousAgentRuleEngine::matchAll($checkpointRules, $facts, null);
        if ($matched === []) {
            AgentRunRecorder::record(
                self::AGENT_ID,
                self::TRIGGER_CHECKPOINT,
                'skip_low_risk',
                (int) $turno->id_turnos,
                null,
                (int) $turno->id_persona,
                null,
                array_merge($facts, ['hours_before' => $hoursBefore]),
                null,
                $this->auditContext($config, $facts, 'skip_low_risk')
            );

            return 'sent';
        }

        foreach ($matched as $rule) {
            $action = (string) ($rule['action'] ?? '');
            $ruleId = (string) ($rule['id'] ?? '');

            if ($action === 'extra_confirm_push') {
                $this->sendExtraConfirm($turno, $config, $facts, $ruleId);
                $releaseHours = (int) ($rule['schedule_release_hours_before'] ?? 0);
                if ($releaseHours > 0 && $this->shouldScheduleRelease($config, (string) $facts['risk_level'])) {
                    $this->scheduler->scheduleRelease($turno, $releaseHours);
                }
            } elseif ($action === 'reminder_push') {
                $this->sendReminder($turno, $config, $facts, $ruleId);
            }
        }

        return 'sent';
    }

    public function processRelease(TurnoNotificacionProgramada $row, Turno $turno): string
    {
        if (!(Yii::$app->params['autonomous_agent_antinoshow_enabled'] ?? true)) {
            return 'cancelled';
        }

        if ($turno->estado !== Turno::ESTADO_PENDIENTE || $this->isAlreadyConfirmed($turno)) {
            return 'cancelled';
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return 'cancelled';
        }
        if ((string) ($config['execution_mode'] ?? 'shadow') !== 'enforce') {
            return 'cancelled';
        }

        $releaseCfg = is_array($config['release_slot'] ?? null) ? $config['release_slot'] : [];
        if (!($releaseCfg['enabled'] ?? false)) {
            return 'cancelled';
        }

        $facts = $this->risk->assess($turno);
        $allowed = is_array($releaseCfg['only_risk_levels'] ?? null) ? $releaseCfg['only_risk_levels'] : ['high'];
        if (!in_array((string) ($facts['risk_level'] ?? ''), $allowed, true)) {
            return 'cancelled';
        }

        $life = new TurnoLifecycleService();
        $life->cancelar(
            $turno,
            Turno::ESTADO_MOTIVO_CANCELADO_SISTEMA,
            'sistema',
            null,
            [
                'razon_cancelacion' => 'A04_SIN_CONFIRMACION',
                'razon_cancelacion_label' => 'Sin confirmación — liberación por política anti no-show',
                'agent_id' => self::AGENT_ID,
            ],
            false,
            \common\models\TurnoEventoAudit::ACTOR_SISTEMA
        );

        $msgs = is_array($config['patient_messages'] ?? null) ? $config['patient_messages'] : [];
        $tpl = is_array($msgs['slot_released'] ?? null) ? $msgs['slot_released'] : [];
        $title = $this->interpolate((string) ($tpl['title'] ?? 'Turno liberado'), $turno);
        $body = $this->interpolate((string) ($tpl['body'] ?? ''), $turno);

        if ($body !== '') {
            (new PushNotificationSender())->sendToPersona(
                (int) $turno->id_persona,
                [
                    'type' => PushNotificationTypes::TURNO_ANTINOSHOW_LIBERADO,
                    'id_turno' => (string) $turno->id_turnos,
                ],
                $title,
                $body
            );
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_RELEASE,
            'release_slot',
            (int) $turno->id_turnos,
            null,
            (int) $turno->id_persona,
            'release_slot',
            $facts,
            null,
            $this->auditContext($config, $facts, 'release_slot')
        );

        return 'sent';
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private function rulesForCheckpoint(array $config, int $hoursBefore): array
    {
        $checkpoints = is_array($config['checkpoints'] ?? null) ? $config['checkpoints'] : [];
        foreach ($checkpoints as $checkpoint) {
            if (!is_array($checkpoint)) {
                continue;
            }
            if ((int) ($checkpoint['hours_before'] ?? 0) !== $hoursBefore) {
                continue;
            }
            $rules = $checkpoint['rules'] ?? [];
            if (!is_array($rules)) {
                return [];
            }
            $out = [];
            foreach ($rules as $rule) {
                if (is_array($rule) && isset($rule['id'], $rule['action'])) {
                    $out[] = $rule;
                }
            }

            return $out;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $facts
     */
    private function shouldScheduleRelease(array $config, string $riskLevel): bool
    {
        $releaseCfg = is_array($config['release_slot'] ?? null) ? $config['release_slot'] : [];
        if (!($releaseCfg['enabled'] ?? false)) {
            return false;
        }
        $allowed = is_array($releaseCfg['only_risk_levels'] ?? null) ? $releaseCfg['only_risk_levels'] : ['high'];

        return in_array($riskLevel, $allowed, true);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $facts
     */
    private function sendExtraConfirm(Turno $turno, array $config, array $facts, string $ruleId): void
    {
        $token = (new TurnoConfirmationService())->ensureConfirmacionToken($turno);
        $msgs = is_array($config['patient_messages'] ?? null) ? $config['patient_messages'] : [];
        $tpl = is_array($msgs['extra_confirm'] ?? null) ? $msgs['extra_confirm'] : [];
        $title = $this->interpolate((string) ($tpl['title'] ?? 'Confirmá tu turno'), $turno);
        $body = $this->interpolate((string) ($tpl['body'] ?? ''), $turno);

        (new PushNotificationSender())->sendToPersona(
            (int) $turno->id_persona,
            [
                'type' => PushNotificationTypes::TURNO_ANTINOSHOW_CONFIRM,
                'id_turno' => (string) $turno->id_turnos,
                'token' => $token,
            ],
            $title,
            $body !== '' ? $body : 'Confirmá asistencia al turno del ' . $turno->fecha
        );

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_CHECKPOINT,
            'extra_confirm_push',
            (int) $turno->id_turnos,
            null,
            (int) $turno->id_persona,
            $ruleId,
            $facts,
            null,
            $this->auditContext($config, $facts, 'extra_confirm_push')
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $facts
     */
    private function sendReminder(Turno $turno, array $config, array $facts, string $ruleId): void
    {
        $msgs = is_array($config['patient_messages'] ?? null) ? $config['patient_messages'] : [];
        $tpl = is_array($msgs['reminder'] ?? null) ? $msgs['reminder'] : [];
        $title = $this->interpolate((string) ($tpl['title'] ?? 'Tu turno es pronto'), $turno);
        $body = $this->interpolate((string) ($tpl['body'] ?? ''), $turno);

        (new PushNotificationSender())->sendToPersona(
            (int) $turno->id_persona,
            [
                'type' => PushNotificationTypes::TURNO_REMINDER,
                'id_turno' => (string) $turno->id_turnos,
                'antinoshow' => '1',
            ],
            $title,
            $body !== '' ? $body : 'Recordá tu turno del ' . $turno->fecha
        );

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_CHECKPOINT,
            'reminder_push',
            (int) $turno->id_turnos,
            null,
            (int) $turno->id_persona,
            $ruleId,
            $facts,
            null,
            $this->auditContext($config, $facts, 'reminder_push')
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $facts
     * @return array<string, mixed>
     */
    private function auditContext(array $config, array $facts, string $action): array
    {
        $canonical = $config;
        $this->sortRecursive($canonical);
        $candidate = is_array($facts['profile_candidate'] ?? null)
            ? $facts['profile_candidate']
            : [];
        $mode = strtoupper((string) ($config['execution_mode'] ?? 'shadow'));
        if (!in_array($mode, ['SHADOW', 'LOW_IMPACT', 'ENFORCE'], true)) {
            $mode = 'SHADOW';
        }

        return [
            'profile_id' => $candidate['profile_id'] ?? null,
            'profile_contract_version' => $candidate['profile_contract_version'] ?? null,
            'policy_id' => self::AGENT_ID,
            'policy_version' => (string) ($config['version'] ?? '1'),
            'policy_hash' => hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE) ?: ''),
            'execution_mode' => $mode,
            'evidence' => $candidate,
            'action' => ['code' => $action],
            'result' => ['legacy_outcome' => $action, 'candidate_mode' => strtolower($mode)],
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
        unset($item);
        if (!array_is_list($value)) {
            ksort($value);
        }
    }

    private function isAlreadyConfirmed(Turno $turno): bool
    {
        return $turno->confirmado_en !== null && trim((string) $turno->confirmado_en) !== '';
    }

    private function interpolate(string $template, Turno $turno): string
    {
        $nombre = '';
        $persona = Persona::findOne((int) $turno->id_persona);
        if ($persona !== null) {
            $nombre = trim((string) $persona->nombre);
        }

        $context = [
            'nombre' => $nombre !== '' ? $nombre : 'paciente',
            'fecha' => (string) $turno->fecha,
            'hora' => substr((string) $turno->hora, 0, 5),
        ];

        $out = $template;
        foreach ($context as $key => $value) {
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }
}
