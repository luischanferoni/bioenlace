<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Agent\AutonomousAgentRuleEngine;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\ProfesionalEfectorServicio;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use common\models\TurnoResolucion;
use Yii;

/**
 * Agente A06 v1: cierra loop de reubicación sin respuesta (cancelar, mantener o escalar).
 */
final class TurnoResolucionLoopCloseAgent
{
    public const AGENT_ID = 'turno-resolucion-loop-close';

    public const TRIGGER_TYPE = 'turno_resolucion_timeout';

    private TurnoResolucionLoopCloseScheduler $scheduler;

    public function __construct(?TurnoResolucionLoopCloseScheduler $scheduler = null)
    {
        $this->scheduler = $scheduler ?? new TurnoResolucionLoopCloseScheduler();
    }

    public function processScheduled(TurnoNotificacionProgramada $row, Turno $turno): string
    {
        if (!(Yii::$app->params['autonomous_agent_resolucion_loop_close_enabled'] ?? true)) {
            return 'cancelled';
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return 'cancelled';
        }

        $res = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
        if ($res === null || $turno->estado !== Turno::ESTADO_EN_RESOLUCION) {
            return 'cancelled';
        }

        $facts = $this->buildFacts($turno, $res, $config);
        $resolved = $this->resolveAction($config, $facts);
        $action = $resolved['action'];
        $ruleId = $resolved['rule_id'];

        switch ($action) {
            case 'escalate_staff':
                $this->escalateStaff($turno, $res, $config, $facts, $ruleId);
                $this->scheduler->cancelPendingLoopClose((int) $turno->id_turnos);
                break;
            case 'keep_in_resolution':
                $this->notifyPatientKeep($turno, $config, $facts, $ruleId);
                $this->scheduler->cancelPendingLoopClose((int) $turno->id_turnos);
                break;
            case 'cancel_turno':
            default:
                $this->cancelTurno($turno, $res, $config, $facts, $ruleId);
                break;
        }

        return 'sent';
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $facts
     * @return array{action: string, rule_id: string}
     */
    private function resolveAction(array $config, array $facts): array
    {
        $rules = AutonomousAgentMetadata::rulesForAgent(self::AGENT_ID);
        $matched = AutonomousAgentRuleEngine::matchAll($rules, $facts, null);
        if ($matched !== []) {
            return [
                'action' => (string) ($matched[0]['action'] ?? 'cancel_turno'),
                'rule_id' => (string) ($matched[0]['id'] ?? ''),
            ];
        }

        return [
            'action' => (string) ($config['default_action'] ?? 'cancel_turno'),
            'rule_id' => '',
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildFacts(Turno $turno, TurnoResolucion $res, array $config): array
    {
        $created = strtotime((string) $res->created_at);
        $hoursOpen = $created > 0 ? (int) floor((time() - $created) / 3600) : 0;

        return [
            'urgency_band' => (string) ($turno->urgency_band ?? ''),
            'resolucion_origen' => (string) $res->origen,
            'horas_sin_respuesta' => $hoursOpen,
            'loop_close_hours' => (int) ($config['loop_close_hours'] ?? 72),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $facts
     */
    private function cancelTurno(Turno $turno, TurnoResolucion $res, array $config, array $facts, string $ruleId): void
    {
        $life = new TurnoLifecycleService();
        $life->cancelar(
            $turno,
            Turno::ESTADO_MOTIVO_CANCELADO_SISTEMA,
            'sistema',
            null,
            [
                'razon_cancelacion' => 'A06_SIN_RESPUESTA',
                'razon_cancelacion_label' => 'Sin respuesta tras reubicación',
                'agent_id' => self::AGENT_ID,
            ],
            false,
            \common\models\TurnoEventoAudit::ACTOR_SISTEMA
        );

        $msgs = is_array($config['patient_messages'] ?? null) ? $config['patient_messages'] : [];
        $tpl = is_array($msgs['cancel_turno'] ?? null) ? $msgs['cancel_turno'] : [];
        $title = $this->interpolate((string) ($tpl['title'] ?? 'Turno liberado'), $turno, $facts);
        $body = $this->interpolate((string) ($tpl['body'] ?? ''), $turno, $facts);

        if ($body !== '') {
            (new PushNotificationSender())->sendToPersona(
                (int) $turno->id_persona,
                [
                    'type' => PushNotificationTypes::TURNO_RESOLUCION_SIN_RESPUESTA,
                    'id_turno' => (string) $turno->id_turnos,
                ],
                $title,
                $body
            );
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'cancel_turno',
            (int) $res->id,
            null,
            (int) $turno->id_persona,
            $ruleId !== '' ? $ruleId : null,
            $facts,
            ['id_turno' => (int) $turno->id_turnos]
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $facts
     */
    private function notifyPatientKeep(Turno $turno, array $config, array $facts, string $ruleId): void
    {
        $msgs = is_array($config['patient_messages'] ?? null) ? $config['patient_messages'] : [];
        $tpl = is_array($msgs['keep_in_resolution'] ?? null) ? $msgs['keep_in_resolution'] : [];
        $title = $this->interpolate((string) ($tpl['title'] ?? 'Seguimos con tu turno'), $turno, $facts);
        $body = $this->interpolate((string) ($tpl['body'] ?? ''), $turno, $facts);

        if ($body !== '') {
            (new PushNotificationSender())->sendToPersona(
                (int) $turno->id_persona,
                [
                    'type' => PushNotificationTypes::TURNO_REQUIERE_REUBICACION,
                    'id_turno' => (string) $turno->id_turnos,
                ],
                $title,
                $body
            );
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'keep_in_resolution',
            null,
            null,
            (int) $turno->id_persona,
            $ruleId !== '' ? $ruleId : null,
            $facts
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $facts
     */
    private function escalateStaff(Turno $turno, TurnoResolucion $res, array $config, array $facts, string $ruleId): void
    {
        $rules = AutonomousAgentMetadata::rulesForAgent(self::AGENT_ID);
        $matched = AutonomousAgentRuleEngine::matchAll($rules, $facts, null);
        $rule = $matched[0] ?? [];
        $staff = is_array($rule['staff'] ?? null) ? $rule['staff'] : [];
        $title = (string) ($staff['title'] ?? 'Resolución sin respuesta');
        $bodyTemplate = (string) ($staff['body_template'] ?? 'Revisá coordinación de agenda.');
        $body = $this->interpolate($bodyTemplate, $turno, $facts);

        $staffPersonaId = $this->personaIdFromPes((int) ($turno->id_profesional_efector_servicio ?? 0));
        if ($staffPersonaId > 0) {
            (new PushNotificationSender())->sendToPersona(
                $staffPersonaId,
                [
                    'type' => PushNotificationTypes::TURNO_RESOLUCION_STAFF_ESCALATE,
                    'id_turno' => (string) $turno->id_turnos,
                    'rule_id' => $ruleId,
                ],
                $title,
                $body,
                true
            );
        } else {
            Yii::info(
                'TurnoResolucionLoopCloseAgent: sin PES para escalar turno=' . (int) $turno->id_turnos,
                'turno-resolucion-loop-close'
            );
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'escalate_staff',
            (int) $res->id,
            null,
            (int) $turno->id_persona,
            $ruleId !== '' ? $ruleId : null,
            $facts,
            ['staff_persona_id' => $staffPersonaId > 0 ? $staffPersonaId : null]
        );
    }

    /**
     * @param array<string, mixed> $facts
     */
    private function interpolate(string $template, Turno $turno, array $facts): string
    {
        $nombre = '';
        $persona = Persona::findOne((int) $turno->id_persona);
        if ($persona !== null) {
            $nombre = trim((string) $persona->nombre);
        }

        $context = array_merge($facts, [
            'nombre' => $nombre !== '' ? $nombre : 'paciente',
            'fecha' => (string) $turno->fecha,
            'hora' => substr((string) $turno->hora, 0, 5),
        ]);

        $out = $template;
        foreach ($context as $key => $value) {
            $out = str_replace('{{' . $key . '}}', (string) $value, $out);
        }

        return $out;
    }

    private function personaIdFromPes(int $idPes): int
    {
        if ($idPes <= 0) {
            return 0;
        }
        $pes = ProfesionalEfectorServicio::findOne($idPes);

        return $pes ? (int) $pes->id_persona : 0;
    }
}
