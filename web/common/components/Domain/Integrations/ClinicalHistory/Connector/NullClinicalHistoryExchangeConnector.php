<?php

namespace common\components\Domain\Integrations\ClinicalHistory\Connector;

use common\components\Domain\Integrations\ClinicalHistory\Contract\ClinicalHistoryExchangeConnector;
use common\components\Domain\Integrations\ClinicalHistory\Dto\ClinicalHistoryExchangeSubmitResult;
use common\models\Clinical\ClinicalHistoryOutboundJob;

/**
 * Conector por defecto: no envía al servidor nacional (modo A / dry-run).
 */
final class NullClinicalHistoryExchangeConnector implements ClinicalHistoryExchangeConnector
{
    public string $connectorKey = 'null';

    public function getConnectorKey(): string
    {
        return $this->connectorKey;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function submitEncounterBundle(
        ClinicalHistoryOutboundJob $job,
        string $bundleJson
    ): ClinicalHistoryExchangeSubmitResult {
        return ClinicalHistoryExchangeSubmitResult::skipped(
            'Export FHIR deshabilitado o conector null (sin envío al servidor nacional).'
        );
    }
}
