<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Platform\AgentRun;
use common\models\Scheduling\TurnoAdvanceCampaign;
use common\models\Scheduling\TurnoAdvanceOffer;
use common\models\TurnoEventoAudit;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * Lecturas admin de campañas/ofertas de adelantamiento (A03).
 */
final class TurnoAdvanceOfferAuditQueryService
{
    private const ADVANCE_EVENT_TYPES = [
        TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_OFFERED,
        TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_DELIVERED,
        TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_OPENED,
        TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_ACCEPTED,
        TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_UNAVAILABLE,
        TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_EXPIRED,
    ];

    /**
     * @param array<string, mixed> $filters
     */
    public function buildCampaignListProvider(array $filters = [], int $pageSize = 30): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->buildCampaignQuery($filters),
            'pagination' => ['pageSize' => $pageSize],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildFailedCampaignListProvider(array $filters = [], int $pageSize = 30): ActiveDataProvider
    {
        $filters['failed_only'] = true;

        return $this->buildCampaignListProvider($filters, $pageSize);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildCampaignQuery(array $filters = []): ActiveQuery
    {
        $q = TurnoAdvanceCampaign::find();

        if (!empty($filters['failed_only'])) {
            $now = date('Y-m-d H:i:s');
            $q->andWhere([
                'or',
                ['estado' => [
                    TurnoAdvanceCampaign::ESTADO_STOPPED,
                    TurnoAdvanceCampaign::ESTADO_EXHAUSTED,
                ]],
                [
                    'and',
                    ['estado' => TurnoAdvanceCampaign::ESTADO_ACTIVE],
                    ['not', ['next_run_at' => null]],
                    ['<', 'next_run_at', $now],
                ],
            ]);
        }

        if (isset($filters['estado']) && is_string($filters['estado']) && $filters['estado'] !== '') {
            $q->andWhere(['estado' => $filters['estado']]);
        }

        if (isset($filters['id_efector']) && (int) $filters['id_efector'] > 0) {
            $q->andWhere(['id_efector' => (int) $filters['id_efector']]);
        }

        if (isset($filters['id_servicio']) && (int) $filters['id_servicio'] > 0) {
            $q->andWhere(['id_servicio' => (int) $filters['id_servicio']]);
        }

        if (isset($filters['id_profesional_efector_servicio'])
            && (int) $filters['id_profesional_efector_servicio'] > 0
        ) {
            $q->andWhere([
                'id_profesional_efector_servicio' => (int) $filters['id_profesional_efector_servicio'],
            ]);
        }

        if (isset($filters['id_cancelled_turno']) && (int) $filters['id_cancelled_turno'] > 0) {
            $q->andWhere(['id_cancelled_turno' => (int) $filters['id_cancelled_turno']]);
        }

        if (isset($filters['date_from']) && is_string($filters['date_from']) && trim($filters['date_from']) !== '') {
            $q->andWhere(['>=', 'created_at', trim($filters['date_from']) . ' 00:00:00']);
        }
        if (isset($filters['date_to']) && is_string($filters['date_to']) && trim($filters['date_to']) !== '') {
            $q->andWhere(['<=', 'created_at', trim($filters['date_to']) . ' 23:59:59']);
        }

        if (isset($filters['slot_fecha']) && is_string($filters['slot_fecha']) && trim($filters['slot_fecha']) !== '') {
            $q->andWhere(['fecha' => trim($filters['slot_fecha'])]);
        }

        return $q;
    }

    /**
     * @return list<TurnoAdvanceOffer>
     */
    public function listOffersForCampaign(int $campaignId): array
    {
        if ($campaignId <= 0) {
            return [];
        }

        /** @var list<TurnoAdvanceOffer> $rows */
        $rows = TurnoAdvanceOffer::find()
            ->where(['id_campaign' => $campaignId])
            ->orderBy(['sequence' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $rows;
    }

    /**
     * Eventos ADVANCE_* del turno cancelado y de turnos candidatos (filtrados por campaign_id en meta cuando existe).
     *
     * @return list<TurnoEventoAudit>
     */
    public function listAdvanceEventsForCampaign(TurnoAdvanceCampaign $campaign): array
    {
        $offers = $this->listOffersForCampaign((int) $campaign->id);
        $turnoIds = [(int) $campaign->id_cancelled_turno];
        foreach ($offers as $offer) {
            $turnoIds[] = (int) $offer->id_turno_candidate;
        }
        if ($campaign->id_turno_filled !== null && (int) $campaign->id_turno_filled > 0) {
            $turnoIds[] = (int) $campaign->id_turno_filled;
        }
        $turnoIds = array_values(array_unique(array_filter($turnoIds, static fn (int $id): bool => $id > 0)));
        if ($turnoIds === []) {
            return [];
        }

        $campaignId = (int) $campaign->id;

        /** @var list<TurnoEventoAudit> $rows */
        $rows = TurnoEventoAudit::find()
            ->where(['id_turno' => $turnoIds])
            ->andWhere([
                'or',
                ['tipo_evento' => self::ADVANCE_EVENT_TYPES],
                ['event_code' => self::ADVANCE_EVENT_TYPES],
            ])
            ->orderBy(['occurred_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(300)
            ->all();

        return $this->filterEventsForCampaign($rows, $campaignId);
    }

    /**
     * @return list<AgentRun>
     */
    public function listAgentRunsForCampaign(int $campaignId): array
    {
        if ($campaignId <= 0) {
            return [];
        }

        /** @var list<AgentRun> $rows */
        $rows = AgentRun::find()
            ->where([
                'agent_id' => TurnoAdvanceOfferAgent::AGENT_ID,
                'trigger_id' => $campaignId,
            ])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(100)
            ->all();

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    public function offerCountsByEstado(int $campaignId): array
    {
        $counts = array_fill_keys(TurnoAdvanceOffer::estadoValues(), 0);
        foreach ($this->listOffersForCampaign($campaignId) as $offer) {
            $estado = (string) $offer->estado;
            if (!isset($counts[$estado])) {
                $counts[$estado] = 0;
            }
            $counts[$estado]++;
        }

        return $counts;
    }

    /**
     * @param list<TurnoEventoAudit> $rows
     * @return list<TurnoEventoAudit>
     */
    private function filterEventsForCampaign(array $rows, int $campaignId): array
    {
        $filtered = [];
        foreach ($rows as $row) {
            $meta = $this->decodeMeta($row->meta_json ?? null);
            if ($meta === []) {
                $filtered[] = $row;
                continue;
            }
            if (!array_key_exists('campaign_id', $meta)) {
                $filtered[] = $row;
                continue;
            }
            if ((int) $meta['campaign_id'] === $campaignId) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(?string $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
