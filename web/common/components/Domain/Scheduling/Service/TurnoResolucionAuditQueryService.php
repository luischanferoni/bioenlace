<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunAuditQueryService;
use common\models\Platform\AgentRun;
use common\models\TurnoNotificacionProgramada;
use yii\data\ActiveDataProvider;

/**
 * Lecturas admin de agentes de resolución (A01 / A02 / A06).
 */
final class TurnoResolucionAuditQueryService
{
    public const AGENT_IDS = [
        'turno-resolucion-shortlist',
        'turno-resolucion-auto-reserva',
        'turno-resolucion-multicanal',
        'turno-resolucion-loop-close',
    ];

    private const NOTIF_TIPOS = [
        TurnoNotificacionProgramada::TIPO_RESOLUCION_MULTICANAL,
        TurnoNotificacionProgramada::TIPO_RESOLUCION_LOOP_CLOSE,
    ];

    private AgentRunAuditQueryService $runs;

    public function __construct(?AgentRunAuditQueryService $runs = null)
    {
        $this->runs = $runs ?? new AgentRunAuditQueryService();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildRunListProvider(array $filters = [], int $pageSize = 40): ActiveDataProvider
    {
        $filters['agent_ids'] = self::AGENT_IDS;
        if (isset($filters['agent_id']) && is_string($filters['agent_id']) && $filters['agent_id'] !== '') {
            if (!in_array($filters['agent_id'], self::AGENT_IDS, true)) {
                $filters['agent_id'] = '__none__';
            }
            unset($filters['agent_ids']);
        }

        return $this->runs->buildListProvider($filters, $pageSize);
    }

    /**
     * Atajo: outcomes de alto impacto o fallo (liberación, cancelación, escalado, apply_failed).
     *
     * @param array<string, mixed> $filters
     */
    public function buildFailedRunListProvider(array $filters = [], int $pageSize = 40): ActiveDataProvider
    {
        $filters['agent_ids'] = self::AGENT_IDS;
        $q = $this->runs->buildQuery($filters);
        $q->andWhere([
            'or',
            ['outcome' => [
                'apply_failed',
                'cancel_turno',
                'escalate_staff',
                'no_unambiguous_candidate',
            ]],
            ['like', 'outcome', 'skipped'],
        ]);

        return new ActiveDataProvider([
            'query' => $q,
            'pagination' => ['pageSize' => $pageSize],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC, 'id' => SORT_DESC],
            ],
        ]);
    }

    /**
     * @return list<TurnoNotificacionProgramada>
     */
    public function listResolucionNotifsForTurno(int $idTurno): array
    {
        if ($idTurno <= 0) {
            return [];
        }

        /** @var list<TurnoNotificacionProgramada> $rows */
        $rows = TurnoNotificacionProgramada::find()
            ->where(['id_turno' => $idTurno, 'tipo' => self::NOTIF_TIPOS])
            ->orderBy(['run_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $rows;
    }

    /**
     * Runs de la familia resolución para el mismo trigger (turno).
     *
     * @return list<AgentRun>
     */
    public function listFamilyRunsForTrigger(int $triggerId): array
    {
        if ($triggerId <= 0) {
            return [];
        }

        /** @var list<AgentRun> $rows */
        $rows = AgentRun::find()
            ->where([
                'agent_id' => self::AGENT_IDS,
                'trigger_id' => $triggerId,
            ])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(100)
            ->all();

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public static function agentLabels(): array
    {
        $all = AgentRunAuditQueryService::knownAgentLabels();
        $out = [];
        foreach (self::AGENT_IDS as $id) {
            $out[$id] = $all[$id] ?? $id;
        }

        return $out;
    }
}
