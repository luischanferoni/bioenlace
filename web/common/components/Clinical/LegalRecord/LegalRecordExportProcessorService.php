<?php

namespace common\components\Clinical\LegalRecord;

use common\components\Core\Service\Push\PushNotificationSender;
use common\components\Core\Service\Push\PushNotificationTypes;
use common\models\Clinical\LegalRecordExportAudit;
use common\models\Clinical\LegalRecordExportRequest;
use common\models\Persona;
use Yii;

/**
 * Procesa la cola de expedientes legales (cron / consola).
 */
final class LegalRecordExportProcessorService
{
    private LegalRecordExportDataCollector $collector;
    private LegalRecordExportPdfService $pdf;

    public function __construct(
        ?LegalRecordExportDataCollector $collector = null,
        ?LegalRecordExportPdfService $pdf = null
    ) {
        $this->collector = $collector ?? new LegalRecordExportDataCollector();
        $this->pdf = $pdf ?? new LegalRecordExportPdfService();
    }

    public function processDueQueue(int $limit = 10): int
    {
        $rows = LegalRecordExportRequest::find()
            ->where(['estado' => LegalRecordExportRequest::ESTADO_PENDIENTE])
            ->orderBy(['id' => SORT_ASC])
            ->limit(max(1, $limit))
            ->all();

        $n = 0;
        foreach ($rows as $row) {
            try {
                if ($this->processOne($row)) {
                    ++$n;
                }
            } catch (\Throwable $e) {
                $this->markFailed($row, $e->getMessage());
                Yii::error($e->getMessage(), 'legal-record-export');
            }
        }

        return $n;
    }

    public function processOne(LegalRecordExportRequest $row): bool
    {
        $row->estado = LegalRecordExportRequest::ESTADO_PROCESANDO;
        $row->intentos = (int) $row->intentos + 1;
        $row->updated_at = date('Y-m-d H:i:s');
        $row->save(false);

        $payload = $this->collector->collect(
            (int) $row->subject_persona_id,
            $row->id_efector ? (int) $row->id_efector : null
        );
        $binary = $this->pdf->renderBinary($payload);

        $dir = $this->pdf->resolveStorageDir();
        $filename = 'expediente-' . (int) $row->id . '-' . date('YmdHis') . '.pdf';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $binary) === false) {
            throw new \RuntimeException('No se pudo guardar el archivo PDF.');
        }

        $now = date('Y-m-d H:i:s');
        $row->estado = LegalRecordExportRequest::ESTADO_LISTO;
        $row->file_path = $path;
        $row->file_size = strlen($binary);
        $row->ready_at = $now;
        $row->ultimo_error = null;
        $row->updated_at = $now;
        $row->save(false);

        LegalRecordExportAudit::registrar(
            (int) $row->id,
            LegalRecordExportAudit::EVENT_GENERADO,
            ['file_size' => $row->file_size]
        );

        $this->notifyRequester($row);

        return true;
    }

    private function notifyRequester(LegalRecordExportRequest $row): void
    {
        $idPersona = (int) ($row->requested_by_persona_id ?? 0);
        if ($idPersona <= 0 && $row->requested_by_user_id > 0) {
            $persona = Persona::findOne(['id_user' => (int) $row->requested_by_user_id]);
            $idPersona = $persona ? (int) $persona->id_persona : 0;
        }
        if ($idPersona <= 0) {
            return;
        }

        (new PushNotificationSender())->sendToPersona(
            $idPersona,
            [
                'type' => PushNotificationTypes::LEGAL_RECORD_EXPORT_READY,
                'request_id' => (string) $row->id,
                'subject_persona_id' => (string) $row->subject_persona_id,
            ],
            'Expediente legal listo',
            'Ya podés descargar el expediente que solicitaste.',
            true
        );
    }

    public function markFailed(LegalRecordExportRequest $row, string $message): void
    {
        $row->estado = LegalRecordExportRequest::ESTADO_FALLIDO;
        $row->ultimo_error = mb_substr($message, 0, 2000);
        $row->updated_at = date('Y-m-d H:i:s');
        $row->save(false);

        LegalRecordExportAudit::registrar(
            (int) $row->id,
            LegalRecordExportAudit::EVENT_FALLIDO,
            ['error' => $row->ultimo_error]
        );
    }
}
