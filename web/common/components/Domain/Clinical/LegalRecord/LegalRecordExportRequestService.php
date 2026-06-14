<?php

namespace common\components\Domain\Clinical\LegalRecord;

use common\models\Clinical\LegalRecordExportAudit;
use common\models\Clinical\LegalRecordExportRequest;
use Yii;

/**
 * Alta y consulta de solicitudes de expediente legal (staff).
 */
final class LegalRecordExportRequestService
{
    private LegalRecordExportAccessService $access;

    public function __construct(?LegalRecordExportAccessService $access = null)
    {
        $this->access = $access ?? new LegalRecordExportAccessService();
    }

    public function createRequest(int $subjectPersonaId, ?int $idEfector = null): LegalRecordExportRequest
    {
        $this->access->assertStaffCanRequest($subjectPersonaId, $idEfector);

        $efectorId = $idEfector > 0 ? (int) $idEfector : (int) Yii::$app->user->getIdEfector();
        $userId = (int) Yii::$app->user->id;
        $now = date('Y-m-d H:i:s');

        $pending = LegalRecordExportRequest::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'requested_by_user_id' => $userId,
                'estado' => [LegalRecordExportRequest::ESTADO_PENDIENTE, LegalRecordExportRequest::ESTADO_PROCESANDO],
            ])
            ->andFilterWhere(['id_efector' => $efectorId > 0 ? $efectorId : null])
            ->one();
        if ($pending !== null) {
            return $pending;
        }

        $row = new LegalRecordExportRequest();
        $row->subject_persona_id = $subjectPersonaId;
        $row->id_efector = $efectorId > 0 ? $efectorId : null;
        $row->requested_by_user_id = $userId;
        $row->requested_by_persona_id = (int) Yii::$app->user->getIdPersona() ?: null;
        $row->estado = LegalRecordExportRequest::ESTADO_PENDIENTE;
        $row->intentos = 0;
        $row->created_at = $now;
        $row->updated_at = $now;
        $row->save(false);

        LegalRecordExportAudit::registrar(
            (int) $row->id,
            LegalRecordExportAudit::EVENT_SOLICITADO,
            [
                'subject_persona_id' => $subjectPersonaId,
                'id_efector' => $row->id_efector,
            ]
        );

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCurrentUser(int $limit = 30, int $offset = 0): array
    {
        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return [];
        }

        $rows = LegalRecordExportRequest::find()
            ->where(['requested_by_user_id' => $userId])
            ->orderBy(['id' => SORT_DESC])
            ->limit(max(1, min(100, $limit)))
            ->offset(max(0, $offset))
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->serializeRequest($row);
        }

        return $out;
    }

    public function getForCurrentUser(int $requestId): ?LegalRecordExportRequest
    {
        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0 || $requestId <= 0) {
            return null;
        }

        return LegalRecordExportRequest::findOne([
            'id' => $requestId,
            'requested_by_user_id' => $userId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRequest(LegalRecordExportRequest $row): array
    {
        return [
            'id' => (int) $row->id,
            'subjectPersonaId' => (int) $row->subject_persona_id,
            'idEfector' => $row->id_efector ? (int) $row->id_efector : null,
            'estado' => (string) $row->estado,
            'readyAt' => $row->ready_at,
            'downloadedAt' => $row->downloaded_at,
            'createdAt' => $row->created_at,
            'fileSize' => $row->file_size ? (int) $row->file_size : null,
            'ultimoError' => $row->ultimo_error,
            'downloadAvailable' => $row->estado === LegalRecordExportRequest::ESTADO_LISTO
                && $row->file_path !== null
                && $row->file_path !== '',
        ];
    }
}
