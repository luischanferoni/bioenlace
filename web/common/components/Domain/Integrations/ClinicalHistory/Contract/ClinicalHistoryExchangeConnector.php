<?php

namespace common\components\Domain\Integrations\ClinicalHistory\Contract;

use common\components\Domain\Integrations\ClinicalHistory\Dto\ClinicalHistoryExchangeSubmitResult;
use common\models\Clinical\ClinicalHistoryOutboundJob;

/**
 * Envío de Bundle FHIR documental al servidor nacional / red de salud.
 *
 * La implementación HTTP real queda en Fase 3 (endpoint TBD).
 */
interface ClinicalHistoryExchangeConnector
{
    public function getConnectorKey(): string;

    public function isEnabled(): bool;

    /**
     * @param string $bundleJson Bundle FHIR serializado
     */
    public function submitEncounterBundle(
        ClinicalHistoryOutboundJob $job,
        string $bundleJson
    ): ClinicalHistoryExchangeSubmitResult;
}
