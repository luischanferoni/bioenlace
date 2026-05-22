# Ingesta pull — laboratorio FHIR

## Objetivo

Traer `DiagnosticReport` del LIS, persistir en BD e idempotizar por proveedor.

## Actores

- Paciente (API sync propio).
- Consola / job (`LaboratorySyncController`).

## Anclas

| Paso | Componente / ruta |
|------|-------------------|
| Registry | `LabConnectorRegistry` |
| Ingesta | `LaboratoryIngestService::syncForPersona` |
| Listado paciente | `GET /api/v1/clinical/laboratory-results/mis-resultados` |
| Sync paciente | `POST /api/v1/clinical/laboratory-results/sincronizar` |
| UI paciente | [intents-laboratorio-paciente.md](./intents-laboratorio-paciente.md) |
| Por encounter | `GET /api/v1/clinical/encounter/<id>/laboratory-results` |
| Consola | `php yii laboratory-sync/persona <id_persona> [connector]` |

---

## Secuencia

1. Resolver `Patient` en el LIS por documento de `personas`.
2. `GET DiagnosticReport?patient={fhirId}`.
3. Por cada informe: upsert `diagnostic_report` + `observation` (`contained` u observaciones embebidas).
4. Enlazar `encounter_id` si aplica.

## Configuración

En `params-local.php`: `laboratoryConnectors.connectors.<key>.clientId` y `clientSecret` (Sianlabs u otro).
