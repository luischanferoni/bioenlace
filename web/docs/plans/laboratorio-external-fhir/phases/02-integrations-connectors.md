# Fase 2 — Conectores HTTP

## Objetivo

Interfaz `FhirLabResultsConnector`, registry y `SianlabsFhirConnector`.

## Entregables

- [x] `Integrations/Laboratory/Contract/FhirLabResultsConnector.php`
- [x] `LabConnectorRegistry` + `params['laboratoryConnectors']`
- [x] `SianlabsFhirConnector` (reemplaza uso directo de `frontend/components/apis/Sianlabs`)

## DoD

Registry resuelve conector por clave; fallo de config → excepción clara.
