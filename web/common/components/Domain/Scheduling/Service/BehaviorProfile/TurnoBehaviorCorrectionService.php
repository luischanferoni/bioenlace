<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\components\Domain\Scheduling\Service\TurnoLifecycleService;
use common\components\Platform\Agent\AgentRunRecorder;
use common\models\Platform\AgentRun;
use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;
use Yii;
use yii\db\Query;

/**
 * Solicitud y resolución de correcciones de no-show (registro inmutable vía agent_run).
 */
final class TurnoBehaviorCorrectionService
{
    public const AGENT_ID = 'turno-behavior-correction';

    public const TRIGGER_REQUEST = 'no_show_correction_request';

    public const OUTCOME_REQUESTED = 'requested';
    public const OUTCOME_ACCEPTED = 'accepted';
    public const OUTCOME_REJECTED = 'rejected';

    public const CLAIM_ATTENDANCE_INCORRECT = 'attendance_was_recorded_incorrectly';
    public const CLAIM_CANCELLATION_ACTOR_INCORRECT = 'cancellation_actor_incorrect';
    public const CLAIM_CONFIRMATION_DELIVERY_INCORRECT = 'confirmation_delivery_incorrect';
    public const CLAIM_APPOINTMENT_NOT_RECOGNIZED = 'appointment_not_recognized';
    public const CLAIM_OTHER = 'other_requires_contact';

    /** @return list<string> */
    public static function claimCodes(): array
    {
        return [
            self::CLAIM_ATTENDANCE_INCORRECT,
            self::CLAIM_CANCELLATION_ACTOR_INCORRECT,
            self::CLAIM_CONFIRMATION_DELIVERY_INCORRECT,
            self::CLAIM_APPOINTMENT_NOT_RECOGNIZED,
            self::CLAIM_OTHER,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function request(
        int $subjectPersonaId,
        int $idTurno,
        string $claimCode,
        ?int $correctedEventId = null,
        string $actorType = TurnoEventoAudit::ACTOR_PACIENTE
    ): array {
        if (!in_array($claimCode, self::claimCodes(), true)) {
            throw new \InvalidArgumentException('Código de reclamo no admitido');
        }
        $turno = Turno::findOne(['id_turnos' => $idTurno, 'id_persona' => $subjectPersonaId]);
        if ($turno === null) {
            throw new \InvalidArgumentException('Turno no encontrado para esta persona');
        }

        $event = $this->resolveNoShowEvent($idTurno, $subjectPersonaId, $correctedEventId);
        if ($event === null) {
            throw new \InvalidArgumentException('No hay un no-show corregible para este turno');
        }

        $existing = AgentRun::find()
            ->where([
                'agent_id' => self::AGENT_ID,
                'trigger_type' => self::TRIGGER_REQUEST,
                'trigger_id' => $idTurno,
                'subject_persona_id' => $subjectPersonaId,
                'outcome' => self::OUTCOME_REQUESTED,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        if ($existing !== null) {
            return [
                'status' => 'ALREADY_REQUESTED',
                'correction_ref' => (int) $existing->id,
                'message' => 'Ya hay una solicitud pendiente para este turno.',
            ];
        }

        $run = AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_REQUEST,
            self::OUTCOME_REQUESTED,
            $idTurno,
            null,
            $subjectPersonaId,
            $claimCode,
            [
                'id_turno' => $idTurno,
                'corrected_event_id' => (int) $event->id,
                'claim_code' => $claimCode,
                'actor_type' => $actorType,
                'id_efector' => (int) ($turno->id_efector ?? 0) ?: null,
            ],
            null,
            [
                'execution_mode' => AgentRun::EXECUTION_SHADOW,
                'evidence' => [
                    'corrected_event_id' => (int) $event->id,
                    'event_code' => (string) $event->event_code,
                ],
                'action' => ['code' => 'request_correction', 'claim_code' => $claimCode],
                'result' => ['status' => self::OUTCOME_REQUESTED],
            ]
        );

        return [
            'status' => 'REQUESTED',
            'correction_ref' => $run !== null ? (int) $run->id : null,
            'message' => 'Recibimos tu solicitud. El equipo del centro la revisará.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listPendingForEfector(int $idEfector): array
    {
        $rows = (new Query())
            ->from(['a' => AgentRun::tableName()])
            ->innerJoin(['t' => Turno::tableName()], 't.id_turnos = a.trigger_id')
            ->select([
                'a.id',
                'a.trigger_id',
                'a.subject_persona_id',
                'a.rule_id',
                'a.facts_json',
                'a.created_at',
                't.fecha',
                't.hora',
                't.id_efector',
            ])
            ->where([
                'a.agent_id' => self::AGENT_ID,
                'a.trigger_type' => self::TRIGGER_REQUEST,
                'a.outcome' => self::OUTCOME_REQUESTED,
                't.id_efector' => $idEfector,
            ])
            ->orderBy(['a.id' => SORT_ASC])
            ->all();

        $items = [];
        foreach ($rows as $row) {
            $facts = json_decode((string) ($row['facts_json'] ?? ''), true);
            $items[] = [
                'correction_ref' => (int) $row['id'],
                'id_turno' => (int) $row['trigger_id'],
                'subject_persona_id' => (int) $row['subject_persona_id'],
                'claim_code' => (string) ($row['rule_id'] ?? ''),
                'corrected_event_id' => is_array($facts) ? ($facts['corrected_event_id'] ?? null) : null,
                'fecha' => (string) $row['fecha'],
                'hora' => substr((string) $row['hora'], 0, 5),
                'requested_at' => (string) $row['created_at'],
                'name' => 'Turno #' . (int) $row['trigger_id'] . ' — ' . (string) ($row['rule_id'] ?? ''),
            ];
        }

        return [
            'status' => 'AVAILABLE',
            'id_efector' => $idEfector,
            'items' => $items,
            'count' => count($items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(
        int $correctionRef,
        string $decision,
        ?string $replacementOutcome = null,
        ?int $idUser = null
    ): array {
        if (!in_array($decision, [self::OUTCOME_ACCEPTED, self::OUTCOME_REJECTED], true)) {
            throw new \InvalidArgumentException('Decisión inválida');
        }

        /** @var AgentRun|null $request */
        $request = AgentRun::findOne([
            'id' => $correctionRef,
            'agent_id' => self::AGENT_ID,
            'trigger_type' => self::TRIGGER_REQUEST,
            'outcome' => self::OUTCOME_REQUESTED,
        ]);
        if ($request === null) {
            throw new \InvalidArgumentException('Solicitud no encontrada o ya resuelta');
        }

        $facts = json_decode((string) ($request->facts_json ?? ''), true);
        if (!is_array($facts)) {
            $facts = [];
        }
        $idTurno = (int) $request->trigger_id;
        $idPersona = (int) $request->subject_persona_id;
        $correctedEventId = (int) ($facts['corrected_event_id'] ?? 0);
        $turno = Turno::findOne(['id_turnos' => $idTurno, 'id_persona' => $idPersona]);
        if ($turno === null) {
            throw new \InvalidArgumentException('Turno asociado no encontrado');
        }

        if ($decision === self::OUTCOME_ACCEPTED) {
            $outcome = $replacementOutcome ?: 'ATTENDED';
            if ($correctedEventId <= 0) {
                throw new \InvalidArgumentException('La solicitud no referencia un evento corregible');
            }
            (new TurnoLifecycleService())->corregirNoShow($turno, $correctedEventId, $outcome, $idUser);
            try {
                (new TurnoBehaviorProfileMaterializerService())->rebuildPersona($idPersona);
            } catch (\Throwable $e) {
                Yii::warning('Rebuild perfil tras corrección: ' . $e->getMessage(), __METHOD__);
            }
        }

        $resolution = AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_REQUEST,
            $decision,
            $idTurno,
            null,
            $idPersona,
            (string) ($request->rule_id ?? ''),
            array_merge($facts, [
                'request_ref' => (int) $request->id,
                'decision' => $decision,
                'replacement_outcome' => $replacementOutcome,
            ]),
            null,
            [
                'execution_mode' => AgentRun::EXECUTION_ENFORCE,
                'evidence' => $facts,
                'action' => ['code' => 'resolve_correction', 'decision' => $decision],
                'result' => [
                    'status' => $decision,
                    'request_ref' => (int) $request->id,
                ],
            ]
        );

        // Cierra la solicitud original de forma idempotente sin borrar evidencia.
        $request->outcome = $decision === self::OUTCOME_ACCEPTED
            ? 'requested_accepted'
            : 'requested_rejected';
        if ($request->hasAttribute('result_json')) {
            $request->result_json = json_encode([
                'status' => $decision,
                'resolution_ref' => $resolution !== null ? (int) $resolution->id : null,
            ], JSON_UNESCAPED_UNICODE);
        }
        $request->save(false);

        return [
            'status' => strtoupper($decision),
            'correction_ref' => (int) $request->id,
            'resolution_ref' => $resolution !== null ? (int) $resolution->id : null,
            'id_turno' => $idTurno,
        ];
    }

    private function resolveNoShowEvent(int $idTurno, int $idPersona, ?int $correctedEventId): ?TurnoEventoAudit
    {
        if ($correctedEventId !== null && $correctedEventId > 0) {
            return TurnoEventoAudit::findOne([
                'id' => $correctedEventId,
                'id_turno' => $idTurno,
                'id_persona' => $idPersona,
                'event_code' => TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
            ]);
        }

        return TurnoEventoAudit::find()
            ->where([
                'id_turno' => $idTurno,
                'id_persona' => $idPersona,
                'event_code' => TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }
}
