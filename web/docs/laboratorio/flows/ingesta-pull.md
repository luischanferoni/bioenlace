# Ingesta pull — laboratorio FHIR

## Objetivo

Traer `DiagnosticReport` del LIS, persistir en BD e idempotizar por proveedor.

## Actores

- Consola / cron (`LaboratorySyncController`).

## Anclas

| Paso | Componente / ruta |
|------|-------------------|
| Registry | `LabConnectorRegistry` |
| Ingesta | `LaboratoryIngestService::syncForPersona` |
| Listado paciente (UI) | `GET /api/v1/clinical/laboratory-result/mis-resultados-como-paciente` (solo BD) |
| Por encounter | `GET /api/v1/clinical/encounter/<id>/laboratory-result` |
| Consola una persona | `php yii laboratory-sync/persona <id_persona> [connector]` |
| Consola lote (cron) | `php yii laboratory-sync/lote [limit] [offset] [connector]` — [ingesta-cron.md](./ingesta-cron.md) |
| Seed demo (sin LIS) | `php yii clinical-seed/laboratory-demo <id_persona>` |

---

## Datos de prueba (sin LIS)

Para probar lista, detalle y PDF sin conectar al laboratorio externo:

```bash
php yii clinical-seed/laboratory-demo 920779
php yii clinical-seed/laboratory-demo-info 920779
```

Crea un `diagnostic_report` con `source_system=demo` y cuatro analitos. El JWT del paciente debe tener `idPersona=920779`.

---

## Secuencia

1. Resolver `Patient` en el LIS por documento de `personas`.
2. `GET DiagnosticReport?patient={fhirId}`.
3. Por cada informe: upsert `diagnostic_report` + `observation` (`contained` u observaciones embebidas).
4. Enlazar `encounter_id` si aplica.

## Configuración

En `params-local.php`: `laboratoryConnectors.connectors.<key>.clientId` y `clientSecret` (Sianlabs u otro).
