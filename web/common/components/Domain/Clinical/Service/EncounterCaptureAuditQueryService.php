<?php

namespace common\components\Domain\Clinical\Service;

use common\models\Clinical\EncounterCapture;
use common\models\Clinical\EncounterCaptureAudit;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * Lecturas admin del trail / drafts de captura (solo consumido por admin).
 */
final class EncounterCaptureAuditQueryService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function buildCaptureListProvider(array $filters = [], int $pageSize = 30): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->buildCaptureQuery($filters),
            'pagination' => ['pageSize' => $pageSize],
            'sort' => [
                'defaultOrder' => ['updated_at' => SORT_DESC],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildFailedCaptureListProvider(array $filters = [], int $pageSize = 30): ActiveDataProvider
    {
        $filters['failed_only'] = true;

        return $this->buildCaptureListProvider($filters, $pageSize);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildCaptureQuery(array $filters = []): ActiveQuery
    {
        $q = EncounterCapture::find();

        if (!empty($filters['failed_only'])) {
            $q->andWhere(['stage' => [
                EncounterCapture::STAGE_STT_FAILED,
                EncounterCapture::STAGE_ANALYSIS_FAILED,
                EncounterCapture::STAGE_SAVE_FAILED,
            ]]);
        }

        if (isset($filters['stage']) && is_string($filters['stage']) && $filters['stage'] !== '') {
            $q->andWhere(['stage' => $filters['stage']]);
        }

        if (isset($filters['subject_persona_id']) && (int) $filters['subject_persona_id'] > 0) {
            $q->andWhere(['subject_persona_id' => (int) $filters['subject_persona_id']]);
        }

        if (isset($filters['created_by_user_id']) && (int) $filters['created_by_user_id'] > 0) {
            $q->andWhere(['created_by_user_id' => (int) $filters['created_by_user_id']]);
        }

        if (isset($filters['parent_type']) && is_string($filters['parent_type']) && $filters['parent_type'] !== '') {
            $q->andWhere(['parent_type' => $filters['parent_type']]);
        }

        if (isset($filters['parent_id']) && (int) $filters['parent_id'] > 0) {
            $q->andWhere(['parent_id' => (int) $filters['parent_id']]);
        }

        if (isset($filters['client_capture_id']) && is_string($filters['client_capture_id'])) {
            $cid = trim($filters['client_capture_id']);
            if ($cid !== '') {
                $q->andWhere(['like', 'client_capture_id', $cid]);
            }
        }

        if (isset($filters['date_from']) && is_string($filters['date_from']) && trim($filters['date_from']) !== '') {
            $q->andWhere(['>=', 'updated_at', trim($filters['date_from']) . ' 00:00:00']);
        }
        if (isset($filters['date_to']) && is_string($filters['date_to']) && trim($filters['date_to']) !== '') {
            $q->andWhere(['<=', 'updated_at', trim($filters['date_to']) . ' 23:59:59']);
        }

        return $q;
    }

    /**
     * @return list<EncounterCaptureAudit>
     */
    public function listEventsForCapture(int $captureId): array
    {
        if ($captureId <= 0) {
            return [];
        }

        /** @var list<EncounterCaptureAudit> $rows */
        $rows = EncounterCaptureAudit::find()
            ->where(['capture_id' => $captureId])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $rows;
    }

    /**
     * Último evento SAVED con meta de aceptación, si existe.
     */
    public function findLatestSavedMeta(int $captureId): ?array
    {
        /** @var EncounterCaptureAudit|null $row */
        $row = EncounterCaptureAudit::find()
            ->where([
                'capture_id' => $captureId,
                'event_type' => EncounterCaptureAudit::EVENT_SAVED,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row !== null ? $row->getMeta() : null;
    }
}
