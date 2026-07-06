# Design — Confianza HAPI → PES

## Principio

La resolución compuesta (SISA + CUIL/DNI + código servicio) **propone** candidatos; la **confianza operativa** viene del catálogo verificado y la reconciliación.

## Capas (entrante)

```
HAPI FHIR (externo)
    → FhirSchedulingInboundConnector (HTTP)
    → FhirAppointmentInboundMapper (Appointment → DTO)
    → FhirSchedulePesResolver (Schedule → PES)
    → TurnoInboundSyncService (persiste turnos)
```

## Capas (saliente)

```
Cambio turnos.estado (lifecycle / resolución / bulk)
    → TurnoFhirOutboundNotifier (fail-soft)
    → FhirAppointmentOutboundSyncService
    → FhirSchedulingInboundConnector::updateAppointmentStatus (PUT Appointment)
    → actualiza turnos.fhir_status local
```

El pull entrante **no** dispara outbound (evita loops). Los hooks solo corren en cambios originados en Bioenlace.

| Capa | Responsabilidad |
|------|-----------------|
| Conector | HTTP GET/PUT, auth OAuth opcional, paginación `_lastUpdated` |
| Mapper entrante | FHIR R4 → DTO; sin reglas de negocio Bioenlace |
| Mapper estados | `FhirAppointmentStatusMapper` bidireccional |
| Resolver | Schedule → PES vía catálogo + fallback compuesto |
| Dominio sync | Alta/actualización turno espejo, `pes_resolution_trust` |
| Dominio outbound | Publicar `Appointment.status` solo si `external_appointment_id` + flags habilitados |

## Niveles de confianza (`pes_resolution_trust`)

| Valor | Significado | Acción |
|-------|-------------|--------|
| `verified` | `integration_schedule_link` verificado y fingerprint OK | Asignar PES |
| `provisional` | Resolver único, sin verificación humana | Turno espejo; flujos que exigen PES bloqueados |
| `unresolved` | 0 o >1 candidatos | **Fail-closed**: sin PES |
| `stale` | Catálogo existía; actores FHIR divergieron | Sin PES automático; alerta staff |

## Reglas fail-closed del resolver compuesto

- Falta identificador obligatorio del contrato → `unresolved`.
- 0 o >1 personas (CUIL/DNI) → `unresolved`.
- 0 o >1 efectores (SISA) → `unresolved`.
- 0 o >1 servicios (código FHIR en catálogo) → `unresolved`.
- 0 o >1 PES activos para la terna → `unresolved`.

Nunca asignar “el más parecido”.

## Catálogo Schedule → PES

Tabla `integration_schedule_link`:

- `source_system` + `external_schedule_id` (único).
- `id_profesional_efector_servicio` confirmado.
- `actor_fingerprint` = SHA-256 de `(cuil|dni, sisa, service_code)` normalizado al verificar.
- `status`: `pending` | `verified` | `stale` | `revoked`.
- `verified_at`, `verified_by_user_id`.

Onboarding: UI staff muestra actores FHIR + PES candidato → confirmación explícita una vez.

## Catálogo servicio FHIR

Tabla `integration_fhir_service_code`:

- `source_system`, `code_system` (URI), `code_value`.
- `id_servicio`, `id_efector_scope` (0 = global).
- Resolución única por `(source, system, code, scope)`; ambigüedad → fail-closed.

API staff: `listar-codigos-servicio-fhir`, `guardar-codigo-servicio-fhir`.

## CUIL en alta PES

`Persona.cuil` obligatorio al crear PES clínico (excepción: servicio `AdminEfector`). Mejora matching `Practitioner` inbound y export FHIR saliente (`FhirClinicalHistoryBundleMapper`).

## Espejo en `turnos`

Clave natural: `(appointment_source_system, external_appointment_id)` — típicamente `msal-nis` + `Appointment.id` HAPI.

Turnos sin paciente local: `id_persona` nullable hasta que inbound resuelva DNI/CUIL.
