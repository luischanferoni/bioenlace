<?php

namespace common\components\Domain\Clinical\HistoryExchange;

use common\models\Clinical\ClinicalHistoryOutboundAudit;
use common\models\Clinical\ClinicalHistoryOutboundJob;

/**
 * Lectura de jobs de export FHIR para API staff.
 */
final class ClinicalHistoryOutboundQueryService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForEncounter(int $encounterId, int $limit = 20): array
    {
        $rows = ClinicalHistoryOutboundJob::find()
            ->andWhere(['encounter_id' => $encounterId])
            ->orderBy(['id' => SORT_DESC])
            ->limit(max(1, min($limit, 50)))
            ->all();

        return array_map([$this, 'serializeJob'], $rows);
    }

    public function findJob(int $jobId): ?ClinicalHistoryOutboundJob
    {
        return ClinicalHistoryOutboundJob::findOne($jobId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function auditTrail(int $jobId, int $limit = 50): array
    {
        $rows = ClinicalHistoryOutboundAudit::find()
            ->andWhere(['job_id' => $jobId])
            ->orderBy(['id' => SORT_ASC])
            ->limit(max(1, min($limit, 100)))
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'event_type' => $row->event_type,
                'meta' => $row->meta_json ? json_decode($row->meta_json, true) : null,
                'created_at' => $row->created_at,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeJob(ClinicalHistoryOutboundJob $row): array
    {
        return [
            'id' => (int) $row->id,
            'encounter_id' => (int) $row->encounter_id,
            'subject_persona_id' => (int) $row->subject_persona_id,
            'efector_id' => $row->efector_id !== null ? (int) $row->efector_id : null,
            'exchange_profile' => $row->exchange_profile,
            'connector_key' => $row->connector_key,
            'estado' => $row->estado,
            'run_at' => $row->run_at,
            'external_id' => $row->external_id,
            'intentos' => (int) $row->intentos,
            'ultimo_error' => $row->ultimo_error,
            'bundle_hash' => $row->bundle_hash,
            'sent_at' => $row->sent_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
