<?php

namespace common\components\Domain\Clinical\Capture\Infrastructure\Persistence;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureId;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRepository;
use common\models\Clinical\EncounterCapture;

/**
 * Adapter AR ↔ aggregate ClinicalCapture.
 */
final class ActiveRecordClinicalCaptureRepository implements ClinicalCaptureRepository
{
    public function findById(ClinicalCaptureId $id): ?ClinicalCapture
    {
        $row = EncounterCapture::findOne(['id' => $id->toInt()]);

        return $row instanceof EncounterCapture ? $this->toAggregate($row) : null;
    }

    public function findByClientCaptureId(string $clientCaptureId): ?ClinicalCapture
    {
        $id = trim($clientCaptureId);
        if ($id === '') {
            return null;
        }
        $row = EncounterCapture::findOne(['client_capture_id' => $id]);

        return $row instanceof EncounterCapture ? $this->toAggregate($row) : null;
    }

    public function findOpenByClientOrId(?string $clientCaptureId, ?int $id): ?ClinicalCapture
    {
        $query = EncounterCapture::find()->andWhere(['stage' => ClinicalCaptureStage::open()]);
        $client = $clientCaptureId !== null ? trim($clientCaptureId) : '';
        if ($client !== '') {
            $query->andWhere(['client_capture_id' => $client]);
        } elseif ($id !== null && $id > 0) {
            $query->andWhere(['id' => $id]);
        } else {
            return null;
        }
        $row = $query->one();

        return $row instanceof EncounterCapture ? $this->toAggregate($row) : null;
    }

    public function save(ClinicalCapture $capture): void
    {
        $row = null;
        if ($capture->id() !== null) {
            $row = EncounterCapture::findOne(['id' => $capture->id()->toInt()]);
        }
        if (!$row instanceof EncounterCapture) {
            $row = EncounterCapture::findOne(['client_capture_id' => $capture->clientCaptureId()]);
        }
        if (!$row instanceof EncounterCapture) {
            $row = new EncounterCapture();
            $row->client_capture_id = $capture->clientCaptureId();
            $row->created_at = date('Y-m-d H:i:s');
        }

        $this->applyAggregateToRow($capture, $row);
        $row->updated_at = date('Y-m-d H:i:s');
        if (!$row->save(false)) {
            throw new \RuntimeException(
                'No se pudo persistir ClinicalCapture: ' . json_encode($row->getErrors())
            );
        }
        if ($capture->id() === null) {
            $capture->assignId(ClinicalCaptureId::fromInt((int) $row->id));
        }
    }

    public function toAggregate(EncounterCapture $row): ClinicalCapture
    {
        return ClinicalCapture::reconstitute(
            ClinicalCaptureId::fromInt((int) $row->id),
            (string) $row->client_capture_id,
            (int) $row->subject_persona_id,
            (int) $row->created_by_user_id,
            (string) $row->stage,
            $row->parent_type,
            $row->parent_id !== null ? (int) $row->parent_id : null,
            $row->encounter_id !== null ? (int) $row->encounter_id : null,
            $row->audio_relative_path,
            $row->audio_mime,
            $row->transcript,
            $row->texto_procesado,
            $row->getSttMeta(),
            $row->getDatosExtraidos(),
            $row->getAnalysisResponse(),
            $row->analysis_cache_token,
            $row->getStagedItemIds(),
            $row->last_error,
            (int) $row->attempts_stt,
            (int) $row->attempts_analysis,
            (int) $row->attempts_save
        );
    }

    /**
     * Expone el AR subyacente para auditoría / paths de audio (borde Infrastructure).
     */
    public function findActiveRecordByAggregate(ClinicalCapture $capture): ?EncounterCapture
    {
        if ($capture->id() !== null) {
            $row = EncounterCapture::findOne(['id' => $capture->id()->toInt()]);
            if ($row instanceof EncounterCapture) {
                return $row;
            }
        }

        $row = EncounterCapture::findOne(['client_capture_id' => $capture->clientCaptureId()]);

        return $row instanceof EncounterCapture ? $row : null;
    }

    private function applyAggregateToRow(ClinicalCapture $capture, EncounterCapture $row): void
    {
        $row->client_capture_id = $capture->clientCaptureId();
        $row->subject_persona_id = $capture->subjectPersonaId();
        $row->created_by_user_id = $capture->createdByUserId();
        $row->stage = $capture->stage();
        $row->parent_type = $capture->parentType();
        $row->parent_id = $capture->parentId();
        $row->encounter_id = $capture->encounterId();
        $row->audio_relative_path = $capture->audioRelativePath();
        $row->audio_mime = $capture->audioMime();
        $row->transcript = $capture->transcript();
        $row->texto_procesado = $capture->textoProcesado();
        $row->setSttMeta($capture->sttMeta() !== [] ? $capture->sttMeta() : null);
        $row->setDatosExtraidos($capture->datosExtraidos() !== [] ? $capture->datosExtraidos() : null);
        $row->setAnalysisResponse($capture->analysisResponse() !== [] ? $capture->analysisResponse() : null);
        $row->analysis_cache_token = $capture->analysisCacheToken();
        $row->setStagedItemIds($capture->stagedItemIds() !== [] ? $capture->stagedItemIds() : null);
        $row->last_error = $capture->lastError();
        $row->attempts_stt = $capture->attemptsStt();
        $row->attempts_analysis = $capture->attemptsAnalysis();
        $row->attempts_save = $capture->attemptsSave();
    }
}
