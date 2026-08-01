<?php

namespace common\components\Platform\Agent;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use common\models\Platform\AgentRun;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Query;

/**
 * Lecturas admin de {@see AgentRun} (todos los agentes autónomos).
 */
final class AgentRunAuditQueryService
{
    /**
     * Etiquetas cortas para UI (agent_id → nombre).
     *
     * @return array<string, string>
     */
    public static function knownAgentLabels(): array
    {
        return [
            'turno-advance-offer' => 'A03 Adelantamiento',
            'turno-antinoshow' => 'A04 Anti no-show',
            'turno-resolucion-shortlist' => 'A01 Shortlist resolución',
            'turno-resolucion-auto-reserva' => 'A01 Auto-reserva',
            'turno-resolucion-multicanal' => 'A02 Multicanal',
            'turno-resolucion-loop-close' => 'A06 Cierre loop',
            'consulta-async-bandeja-prioridad' => 'H01 Bandeja async',
            'reserva-triage-post-cupo-routing' => 'A05 Ruteo post-triage',
            'post-lab-classification' => 'B03 Post-lab',
            'lab-encounter-link' => 'E01 Lab→encounter',
            'integration-retry' => 'E02 Retry integración',
            'prescription-rdi-pre-submit' => 'E03 RDI pre-submit',
            'care-followup-branching' => 'B01 Follow-up branching',
            'post-discharge-followup' => 'B02 Post-alta',
            'internacion-cama-sugerencia' => 'F02 Cama sugerida',
        ];
    }

    /**
     * @return list<string>
     */
    public function listAgentIds(): array
    {
        $fromYaml = [];
        $dir = ProductMetadataPaths::autonomousAgentsDir();
        if (is_dir($dir)) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [] as $file) {
                $fromYaml[] = basename((string) $file, '.yaml');
            }
        }

        $fromDb = (new Query())
            ->select('agent_id')
            ->from(AgentRun::tableName())
            ->distinct()
            ->column();

        $ids = array_values(array_unique(array_merge($fromYaml, array_map('strval', $fromDb))));
        sort($ids);

        return $ids;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildListProvider(array $filters = [], int $pageSize = 40): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->buildQuery($filters),
            'pagination' => ['pageSize' => $pageSize],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC, 'id' => SORT_DESC],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildQuery(array $filters = []): ActiveQuery
    {
        $q = AgentRun::find();

        if (isset($filters['agent_id']) && is_string($filters['agent_id']) && $filters['agent_id'] !== '') {
            $q->andWhere(['agent_id' => $filters['agent_id']]);
        }

        if (isset($filters['agent_ids']) && is_array($filters['agent_ids']) && $filters['agent_ids'] !== []) {
            $q->andWhere(['agent_id' => array_values($filters['agent_ids'])]);
        }

        if (isset($filters['outcome']) && is_string($filters['outcome']) && $filters['outcome'] !== '') {
            $q->andWhere(['outcome' => $filters['outcome']]);
        }

        if (isset($filters['trigger_type']) && is_string($filters['trigger_type']) && $filters['trigger_type'] !== '') {
            $q->andWhere(['trigger_type' => $filters['trigger_type']]);
        }

        if (isset($filters['trigger_id']) && (int) $filters['trigger_id'] > 0) {
            $q->andWhere(['trigger_id' => (int) $filters['trigger_id']]);
        }

        if (isset($filters['subject_persona_id']) && (int) $filters['subject_persona_id'] > 0) {
            $q->andWhere(['subject_persona_id' => (int) $filters['subject_persona_id']]);
        }

        if (isset($filters['encounter_id']) && (int) $filters['encounter_id'] > 0) {
            $q->andWhere(['encounter_id' => (int) $filters['encounter_id']]);
        }

        if (isset($filters['date_from']) && is_string($filters['date_from']) && trim($filters['date_from']) !== '') {
            $q->andWhere(['>=', 'created_at', trim($filters['date_from']) . ' 00:00:00']);
        }
        if (isset($filters['date_to']) && is_string($filters['date_to']) && trim($filters['date_to']) !== '') {
            $q->andWhere(['<=', 'created_at', trim($filters['date_to']) . ' 23:59:59']);
        }

        return $q;
    }

    /**
     * @return list<AgentRun>
     */
    public function listSiblingRuns(AgentRun $run, int $limit = 50): array
    {
        if ($run->trigger_id === null || (int) $run->trigger_id <= 0) {
            return [];
        }

        /** @var list<AgentRun> $rows */
        $rows = AgentRun::find()
            ->where([
                'agent_id' => $run->agent_id,
                'trigger_id' => (int) $run->trigger_id,
            ])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit($limit)
            ->all();

        return $rows;
    }

    public static function agentLabel(string $agentId): string
    {
        $labels = self::knownAgentLabels();

        return $labels[$agentId] ?? $agentId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeJson(?string $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
