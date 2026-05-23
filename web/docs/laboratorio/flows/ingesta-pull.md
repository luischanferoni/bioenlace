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
