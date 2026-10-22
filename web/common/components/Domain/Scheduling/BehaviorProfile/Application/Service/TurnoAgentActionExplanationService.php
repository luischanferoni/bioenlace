<?php

namespace common\components\Domain\Scheduling\BehaviorProfile\Application\Service;

use common\components\Domain\Scheduling\BehaviorProfile\Application\Catalog\TurnoAgentActionExplanationCatalog;
use common\models\Platform\AgentRun;
use common\models\Scheduling\PersonaTurnosPerfil;
use common\models\Scheduling\Turno;
use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoAntinoshowAgent;

/**
 * Explicación neutral de una acción anti no-show para el titular.
 */
final class TurnoAgentActionExplanationService
{
    private TurnoAgentActionExplanationCatalog $copyCatalog;

    public function __construct(?TurnoAgentActionExplanationCatalog $copyCatalog = null)
    {
        $this->copyCatalog = $copyCatalog ?? new TurnoAgentActionExplanationCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function explainOwnAction(int $idPersona, int $idTurno, ?int $agentRunId = null): array
    {
        if ($idPersona <= 0 || $idTurno <= 0) {
            throw new \InvalidArgumentException('id_persona e id_turno son obligatorios');
        }

        $turno = Turno::findOne(['id_turnos' => $idTurno, 'id_persona' => $idPersona]);
        if ($turno === null) {
            throw new \InvalidArgumentException('Turno no encontrado para esta persona');
        }

        $q = AgentRun::find()
            ->where([
                'agent_id' => TurnoAntinoshowAgent::AGENT_ID,
                'subject_persona_id' => $idPersona,
                'trigger_id' => $idTurno,
            ])
            ->orderBy(['id' => SORT_DESC]);
        if ($agentRunId !== null && $agentRunId > 0) {
            $q->andWhere(['id' => $agentRunId]);
        }
        /** @var AgentRun|null $run */
        $run = $q->one();
        if ($run === null) {
            return [
                'status' => 'UNAVAILABLE',
                'message' => $this->copyCatalog->unavailableMessage(),
            ];
        }

        $evidence = $this->decodeJson($run->hasAttribute('evidence_json') ? $run->evidence_json : null);
        $action = $this->decodeJson($run->hasAttribute('action_json') ? $run->action_json : null);
        $result = $this->decodeJson($run->hasAttribute('result_json') ? $run->result_json : null);

        $profileId = $run->hasAttribute('profile_id') ? (int) ($run->profile_id ?? 0) : 0;
        $snapshotStatus = 'NO_PROFILE';
        if ($profileId > 0) {
            $profile = PersonaTurnosPerfil::findOne($profileId);
            if ($profile === null) {
                $snapshotStatus = 'PROFILE_MISSING';
            } elseif ((int) ($profile->is_current ?? 0) === 1) {
                $snapshotStatus = 'CURRENT_SNAPSHOT';
            } else {
                $snapshotStatus = 'SUPERSEDED_SNAPSHOT';
            }
        }

        $actionCode = (string) ($action['code'] ?? $run->outcome);
        $copy = $this->copyCatalog->forAction($actionCode, $snapshotStatus === 'SUPERSEDED_SNAPSHOT');

        return [
            'status' => 'AVAILABLE',
            'decision_ref' => (int) $run->id,
            'appointment_ref' => $idTurno,
            'occurred_at' => (string) $run->created_at,
            'action_code' => $actionCode,
            'action_label' => $copy['action_label'],
            'policy_version' => $run->hasAttribute('policy_version')
                ? (string) ($run->policy_version ?? '')
                : null,
            'profile_contract_version' => $run->hasAttribute('profile_contract_version')
                ? ($run->profile_contract_version !== null ? (string) $run->profile_contract_version : null)
                : null,
            'profile_id' => $profileId > 0 ? $profileId : null,
            'snapshot_status' => $snapshotStatus,
            'execution_mode' => $run->hasAttribute('execution_mode')
                ? (string) ($run->execution_mode ?? '')
                : null,
            'evidence' => [
                'profile_status' => $evidence['status'] ?? null,
                'no_show_count' => $evidence['no_show_count'] ?? null,
                'attended_count_efector' => $evidence['attended_count_efector'] ?? null,
            ],
            'result' => [
                'legacy_outcome' => $result['legacy_outcome'] ?? $run->outcome,
                'candidate_mode' => $result['candidate_mode'] ?? null,
            ],
            '_text' => $copy['explanation_text'],
            'disclaimer' => $copy['disclaimer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
