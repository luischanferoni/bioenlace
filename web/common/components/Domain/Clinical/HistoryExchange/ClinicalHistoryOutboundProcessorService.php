<?php

namespace common\components\Domain\Clinical\HistoryExchange;

use common\components\Domain\Integrations\ClinicalHistory\ClinicalHistoryExchangeRegistry;
use common\components\Domain\Integrations\ClinicalHistory\Mapper\FhirClinicalHistoryBundleMapper;
use common\models\Clinical\ClinicalHistoryOutboundAudit;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Procesa la cola de export FHIR (cron / consola).
 */
final class ClinicalHistoryOutboundProcessorService
{
    private FhirClinicalHistoryBundleMapper $mapper;

    public function __construct(?FhirClinicalHistoryBundleMapper $mapper = null)
    {
        $this->mapper = $mapper ?? new FhirClinicalHistoryBundleMapper();
    }

    public function processDueQueue(int $limit = 20): int
    {
        $config = ClinicalHistoryExchangeRegistry::config();
        $limit = max(1, min($limit, (int) ($config['retry']['batch_limit'] ?? 20)));
        $now = date('Y-m-d H:i:s');

        $rows = ClinicalHistoryOutboundJob::find()
            ->where(['in', 'estado', [
                ClinicalHistoryOutboundJob::ESTADO_PENDIENTE,
                ClinicalHistoryOutboundJob::ESTADO_FALLIDO,
            ]])
            ->andWhere(['<=', 'run_at', $now])
            ->orderBy(['run_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit($limit)
            ->all();

        $n = 0;
        foreach ($rows as $row) {
            try {
                if ($this->processOne($row)) {
                    ++$n;
                }
            } catch (\Throwable $e) {
                $this->markFailed($row, $e->getMessage(), true);
                Yii::error($e->getMessage(), 'clinical-history-exchange');
            }
        }

        return $n;
    }

    public function processOne(ClinicalHistoryOutboundJob $row): bool
    {
        $encounter = Encounter::findOne(['id' => (int) $row->encounter_id, 'deleted_at' => null]);
        if ($encounter === null) {
            $this->markDead($row, 'Encounter no encontrado o eliminado.');

            return false;
        }

        $now = date('Y-m-d H:i:s');
        $row->estado = ClinicalHistoryOutboundJob::ESTADO_PROCESANDO;
        $row->intentos = (int) $row->intentos + 1;
        $row->updated_at = $now;
        $row->save(false);

        ClinicalHistoryOutboundAudit::registrar(
            (int) $row->id,
            ClinicalHistoryOutboundAudit::EVENT_PROCESANDO,
            ['intento' => (int) $row->intentos]
        );

        $bundle = $this->mapper->buildForEncounter($encounter, (string) $row->exchange_profile);
        $bundleJson = json_encode($bundle, JSON_UNESCAPED_UNICODE);
        if ($bundleJson === false || $bundleJson === '' || $bundleJson === '[]') {
            $this->markDead($row, 'Bundle FHIR vacío o inválido.');

            return false;
        }

        $row->bundle_hash = hash('sha256', $bundleJson);
        $config = ClinicalHistoryExchangeRegistry::config();
        if (!empty($config['log_bundle_snapshot'])) {
            $row->bundle_json = $bundleJson;
        }

        $connector = ClinicalHistoryExchangeRegistry::get($row->connector_key);
        $result = $connector->submitEncounterBundle($row, $bundleJson);

        if ($result->skipped) {
            $row->estado = ClinicalHistoryOutboundJob::ESTADO_OMITIDO;
            $row->ultimo_error = $result->message;
            $row->updated_at = date('Y-m-d H:i:s');
            $row->save(false);
            ClinicalHistoryOutboundAudit::registrar(
                (int) $row->id,
                ClinicalHistoryOutboundAudit::EVENT_OMITIDO,
                ['message' => $result->message]
            );

            return true;
        }

        if ($result->success) {
            $row->estado = ClinicalHistoryOutboundJob::ESTADO_ENVIADO;
            $row->external_id = $result->externalId;
            $row->ultimo_error = null;
            $row->sent_at = date('Y-m-d H:i:s');
            $row->updated_at = $row->sent_at;
            if (empty($row->bundle_json)) {
                $row->bundle_json = $bundleJson;
            }
            $row->save(false);
            ClinicalHistoryOutboundAudit::registrar(
                (int) $row->id,
                ClinicalHistoryOutboundAudit::EVENT_ENVIADO,
                ['external_id' => $result->externalId]
            );

            return true;
        }

        $this->markFailed($row, (string) $result->message, $result->retryable);

        return false;
    }

    private function markFailed(ClinicalHistoryOutboundJob $row, string $message, bool $retryable): void
    {
        $config = ClinicalHistoryExchangeRegistry::config();
        $maxAttempts = (int) ($config['retry']['max_attempts'] ?? 5);
        $backoff = $config['retry']['backoff_seconds'] ?? [60, 300, 900, 3600, 14400];
        if (!is_array($backoff)) {
            $backoff = [60, 300, 900, 3600, 14400];
        }

        $row->ultimo_error = $message;
        $row->updated_at = date('Y-m-d H:i:s');

        if (!$retryable || (int) $row->intentos >= $maxAttempts) {
            $this->markDead($row, $message);

            return;
        }

        $idx = min((int) $row->intentos - 1, count($backoff) - 1);
        $wait = (int) ($backoff[$idx] ?? 300);
        $row->estado = ClinicalHistoryOutboundJob::ESTADO_FALLIDO;
        $row->run_at = date('Y-m-d H:i:s', time() + max(1, $wait));
        $row->save(false);

        ClinicalHistoryOutboundAudit::registrar(
            (int) $row->id,
            ClinicalHistoryOutboundAudit::EVENT_FALLIDO,
            ['message' => $message, 'next_run_at' => $row->run_at]
        );
    }

    private function markDead(ClinicalHistoryOutboundJob $row, string $message): void
    {
        $row->estado = ClinicalHistoryOutboundJob::ESTADO_MUERTO;
        $row->ultimo_error = $message;
        $row->updated_at = date('Y-m-d H:i:s');
        $row->save(false);

        ClinicalHistoryOutboundAudit::registrar(
            (int) $row->id,
            ClinicalHistoryOutboundAudit::EVENT_MUERTO,
            ['message' => $message]
        );
    }
}
