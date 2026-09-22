<?php

namespace common\components\Domain\Clinical\LegalExport\Application\UseCase;

use common\components\Domain\Clinical\LegalExport\Application\Authorization\LegalExportAccessService;
use common\models\Clinical\LegalExportAudit;
use common\models\Clinical\LegalExportRequest;
use Yii;

/**
 * Alta y consulta de solicitudes de expediente legal (staff).
 */
final class RequestLegalExport
{
    private LegalExportAccessService $access;

    public function __construct(?LegalExportAccessService $access = null)
    {
        $this->access = $access ?? new LegalExportAccessService();
    }

    public function createRequest(int $subjectPersonaId, ?int $idEfector = null): LegalExportRequest
    {
        $this->access->assertStaffCanRequest($subjectPersonaId, $idEfector);

        $efectorId = $idEfector > 0 ? (int) $idEfector : (int) Yii::$app->user->getIdEfector();
        $userId = (int) Yii::$app->user->id;
        $now = date('Y-m-d H:i:s');

        $pending = LegalExportRequest::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'requested_by_user_id' => $userId,
                'estado' => [LegalExportRequest::ESTADO_PENDIENTE, LegalExportRequest::ESTADO_PROCESANDO],
            ])
            ->andFilterWhere(['id_efector' => $efectorId > 0 ? $efectorId : null])
            ->one();
        if ($pending !== null) {
            return $pending;
        }

        $row = new LegalExportRequest();
        $row->subject_persona_id = $subjectPersonaId;
        $row->id_efector = $efectorId > 0 ? $efectorId : null;
        $row->requested_by_user_id = $userId;
        $row->requested_by_persona_id = (int) Yii::$app->user->getIdPersona() ?: null;
        $row->estado = LegalExportRequest::ESTADO_PENDIENTE;
        $row->intentos = 0;
        $row->created_at = $now;
        $row->updated_at = $now;
        $row->save(false);

        LegalExportAudit::registrar(
            (int) $row->id,
            LegalExportAudit::EVENT_SOLICITADO,
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

        $rows = LegalExportRequest::find()
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

    public function getForCurrentUser(int $requestId): ?LegalExportRequest
    {
        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0 || $requestId <= 0) {
            return null;
        }

        return LegalExportRequest::findOne([
            'id' => $requestId,
            'requested_by_user_id' => $userId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRequest(LegalExportRequest $row): array
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
            'downloadAvailable' => $row->estado === LegalExportRequest::ESTADO_LISTO
                && $row->file_path !== null
                && $row->file_path !== '',
        ];
    }
}
