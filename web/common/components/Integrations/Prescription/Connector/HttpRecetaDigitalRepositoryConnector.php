<?php

namespace common\components\Integrations\Prescription\Connector;

use common\components\Integrations\Prescription\Contract\RecetaDigitalRepositoryConnector;
use common\components\Integrations\Prescription\Dto\PrescriptionRepositoryRegisterResult;
use common\components\Integrations\Prescription\Exception\RecetaDigitalRepositoryException;
use common\models\Clinical\ElectronicPrescription;

/**
 * Esqueleto HTTP hacia repositorio MSAL RDI.
 *
 * Con `enabled => false` (default) no realiza llamadas. Activar en params-local cuando exista credencial y contrato.
 */
final class HttpRecetaDigitalRepositoryConnector implements RecetaDigitalRepositoryConnector
{
    public string $connectorKey = 'msal-rdi';

    /** @var bool Activar en params-local cuando haya endpoint y credenciales reales */
    public bool $enabled = false;

    public ?string $baseUrl = null;

    public ?string $tokenUrl = null;

    public ?string $clientId = null;

    public ?string $clientSecret = null;

    public function getConnectorKey(): string
    {
        return $this->connectorKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function registerIssuedPrescription(
        ElectronicPrescription $rx,
        string $fhirBundleJson
    ): PrescriptionRepositoryRegisterResult {
        if (!$this->isEnabled()) {
            return PrescriptionRepositoryRegisterResult::skipped(
                $this->getConnectorKey(),
                'Conector msal-rdi deshabilitado (enabled=false).'
            );
        }

        if ($this->baseUrl === null || trim($this->baseUrl) === '') {
            throw new RecetaDigitalRepositoryException('recetaDigitalRepository: baseUrl requerido para msal-rdi.');
        }

        // Fase 3: POST Bundle al endpoint nacional, parsear id de repositorio, manejar errores HTTP.
        throw new RecetaDigitalRepositoryException(
            'Envío al repositorio nacional aún no implementado. Configure el conector cuando MSAL habilite el entorno.'
        );
    }
}
