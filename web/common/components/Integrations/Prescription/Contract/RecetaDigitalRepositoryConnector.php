<?php

namespace common\components\Integrations\Prescription\Contract;

use common\components\Integrations\Prescription\Dto\PrescriptionRepositoryRegisterResult;
use common\models\Clinical\ElectronicPrescription;

/**
 * Conexión al repositorio nacional de recetas (MSAL RDI / Receta Digital).
 *
 * Implementaciones reales harán POST del Bundle FHIR; hoy solo Null y HTTP deshabilitado.
 */
interface RecetaDigitalRepositoryConnector
{
    public function getConnectorKey(): string;

    public function isEnabled(): bool;

  /**
   * Registra una receta ya emitida en Bioenlace en el repositorio externo.
   *
   * @param string $fhirBundleJson Bundle snapshot (perfil recetaDigitalRegistroRecetaAR)
   */
  public function registerIssuedPrescription(
      ElectronicPrescription $rx,
      string $fhirBundleJson
  ): PrescriptionRepositoryRegisterResult;
}
