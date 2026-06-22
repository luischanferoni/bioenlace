<?php

namespace common\components\Domain\Clinical\HistoryExchange;

use common\components\Domain\Integrations\ClinicalHistory\ClinicalHistoryExchangeRegistry;
use common\components\Domain\Integrations\ClinicalHistory\Contract\ClinicalHistorySubmissionStatusConnector;
use common\models\Clinical\ClinicalHistoryOutboundAudit;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use Yii;

/**
 * Concilia jobs ENVIADO sin acuse definitivo (Fase 4).
 */
final class ClinicalHistoryOutboundReconcileService
{
    public function reconcileDue(int $limit = 50): int
    {
        if (!ClinicalHistoryExchangeRegistry::isMasterEnabled()) {
            return 0;
        }

        $config = ClinicalHistoryExchangeRegistry::config();
        $limit = max(1, min($limit, (int) ($config['reconcile']['batch_limit'] ?? 50)));

        $rows = ClinicalHistoryOutboundJob::find()
            ->where(['estado' => ClinicalHistoryOutboundJob::ESTADO_ENVIADO])
            ->andWhere([
                'or',
                ['external_id' => null],
                ['external_id' => ''],
                ['like', 'external_id', 'bioenlace-job-%', false],
            ])
            ->orderBy(['sent_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit($limit)
            ->all();

        $n = 0;
        foreach ($rows as $row) {
            try {
                if ($this->reconcileOne($row)) {
                    ++$n;
                }
            } catch (\Throwable $e) {
                Yii::warning($e->getMessage(), 'clinical-history-exchange');
            }
        }

        return $n;
    }

    public function reconcileOne(ClinicalHistoryOutboundJob $row): bool
    {
        $connector = ClinicalHistoryExchangeRegistry::get($row->connector_key);
        if (!$connector instanceof ClinicalHistorySubmissionStatusConnector) {
            return false;
        }

        $result = $connector->pollSubmissionStatus($row);
        if (!$result->supported || !$result->found || $result->externalId === null || $result->externalId === '') {
            return false;
        }

        $previous = (string) ($row->external_id ?? '');
        if ($previous === $result->externalId) {
            return false;
        }

        $row->external_id = $result->externalId;
        $row->updated_at = date('Y-m-d H:i:s');
        $row->save(false);

        ClinicalHistoryOutboundAudit::registrar(
            (int) $row->id,
            ClinicalHistoryOutboundAudit::EVENT_RECONCILIADO,
            [
                'external_id' => $result->externalId,
                'status' => $result->status,
                'previous_external_id' => $previous !== '' ? $previous : null,
            ]
        );

        return true;
    }
}
