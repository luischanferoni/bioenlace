<?php

namespace common\components\Integrations\Laboratory\Contract;

/**
 * Cliente pull hacia un LIS que expone FHIR (DiagnosticReport / Observation).
 */
interface FhirLabResultsConnector
{
    public function getConnectorKey(): string;

    /**
     * Resuelve el id FHIR del Patient en el LIS (p. ej. por número de documento).
     */
    public function resolvePatientFhirId(string $documentNumber): ?string;

    /**
     * Bundle o lista de DiagnosticReport para un patient FHIR id.
     *
     * @return array<string, mixed> respuesta FHIR (Bundle o array)
     */
    public function fetchDiagnosticReports(string $patientFhirId): array;
}
