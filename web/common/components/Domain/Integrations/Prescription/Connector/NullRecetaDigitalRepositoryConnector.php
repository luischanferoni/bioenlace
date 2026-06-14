<?php

namespace common\components\Domain\Integrations\Prescription\Connector;

use common\components\Domain\Integrations\Prescription\Contract\RecetaDigitalRepositoryConnector;
use common\components\Domain\Integrations\Prescription\Dto\PrescriptionRepositoryRegisterResult;
use common\models\Clinical\ElectronicPrescription;

/**
 * Conector por defecto: no envía al repositorio nacional (modo A interno).
 */
final class NullRecetaDigitalRepositoryConnector implements RecetaDigitalRepositoryConnector
{
    public string $connectorKey = 'null';

    public function getConnectorKey(): string
    {
        return $this->connectorKey;
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function registerIssuedPrescription(
        ElectronicPrescription $rx,
        string $fhirBundleJson
    ): PrescriptionRepositoryRegisterResult {
        return PrescriptionRepositoryRegisterResult::skipped(
            $this->getConnectorKey(),
            'Repositorio nacional deshabilitado (conector null).'
        );
    }
}
