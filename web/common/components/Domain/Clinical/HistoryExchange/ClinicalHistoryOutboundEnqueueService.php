<?php

namespace common\components\Domain\Clinical\HistoryExchange;

use common\components\Domain\Integrations\ClinicalHistory\ClinicalHistoryExchangeRegistry;
use common\components\Domain\Integrations\ClinicalHistory\Mapper\FhirClinicalHistoryBundleMapper;
use common\models\Clinical\ClinicalHistoryOutboundAudit;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Encola export FHIR al finalizar encounter (si params lo permiten).
 */
final class ClinicalHistoryOutboundEnqueueService
{
    public function scheduleIfApplicable(Encounter $encounter): ?ClinicalHistoryOutboundJob
    {
        if (!ClinicalHistoryExchangeRegistry::isMasterEnabled()) {
            return null;
        }

        if ($encounter->status !== \common\components\Domain\Clinical\Enum\EncounterStatus::FINISHED) {
            return null;
        }

        $config = ClinicalHistoryExchangeRegistry::config();
        $classes = $config['encounter_classes'] ?? ['AMB', 'EMER', 'IMP'];
        if (!is_array($classes) || !in_array($encounter->encounter_class, $classes, true)) {
            return null;
        }

        if ((int) $encounter->subject_persona_id <= 0) {
            return null;
        }

        $efectorId = $encounter->efector_id !== null ? (int) $encounter->efector_id : null;
        $excluded = $config['excluded_efector_ids'] ?? [];
        if ($efectorId !== null && is_array($excluded) && in_array($efectorId, $excluded, true)) {
            return null;
        }

        $allowed = $config['allowed_efector_ids'] ?? null;
        if (is_array($allowed) && $allowed !== [] && $efectorId !== null && !in_array($efectorId, $allowed, true)) {
            return null;
        }

        $profile = (string) ($config['exchange_profile'] ?? ClinicalHistoryOutboundJob::PROFILE_ENCOUNTER_DOCUMENT_V1);
        $connectorKey = (string) ($config['default'] ?? 'null');

        $existingSent = ClinicalHistoryOutboundJob::find()
            ->andWhere([
                'encounter_id' => (int) $encounter->id,
                'exchange_profile' => $profile,
                'estado' => ClinicalHistoryOutboundJob::ESTADO_ENVIADO,
            ])
            ->exists();
        if ($existingSent) {
            return null;
        }

        $delay = (int) ($config['retry']['delay_after_finalize_seconds'] ?? 120);
        $now = date('Y-m-d H:i:s');
        $runAt = date('Y-m-d H:i:s', time() + max(0, $delay));

        $row = ClinicalHistoryOutboundJob::find()
            ->andWhere([
                'encounter_id' => (int) $encounter->id,
                'exchange_profile' => $profile,
            ])
            ->andWhere(['not in', 'estado', [ClinicalHistoryOutboundJob::ESTADO_MUERTO]])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if (!$row instanceof ClinicalHistoryOutboundJob) {
            $row = new ClinicalHistoryOutboundJob();
            $row->encounter_id = (int) $encounter->id;
            $row->exchange_profile = $profile;
            $row->created_at = $now;
            $row->intentos = 0;
        }

        if ($row->estado === ClinicalHistoryOutboundJob::ESTADO_ENVIADO) {
            return $row;
        }

        $row->subject_persona_id = (int) $encounter->subject_persona_id;
        $row->efector_id = $efectorId;
        $row->connector_key = $connectorKey;
        $row->estado = ClinicalHistoryOutboundJob::ESTADO_PENDIENTE;
        $row->run_at = $runAt;
        $row->ultimo_error = null;
        $row->updated_at = $now;
        $row->save(false);

        ClinicalHistoryOutboundAudit::registrar(
            (int) $row->id,
            ClinicalHistoryOutboundAudit::EVENT_ENCUEUED,
            ['encounter_id' => (int) $encounter->id, 'run_at' => $runAt]
        );

        return $row;
    }
}
