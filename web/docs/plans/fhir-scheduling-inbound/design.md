# Design — Confianza HAPI → PES

## Principio

La resolución compuesta (SISA + CUIL/DNI + código servicio) **propone** candidatos; la **confianza operativa** viene del catálogo verificado y la reconciliación.

## Capas

```
HAPI FHIR (externo)
    → FhirSchedulingInboundConnector (HTTP)
    → FhirAppointmentInboundMapper (Appointment → DTO)
    → FhirSchedulePesResolver (Schedule → PES)
    → TurnoInboundSyncService (persiste turnos)
```

| Capa | Responsabilidad |
|------|-----------------|
| Conector | HTTP, auth, paginación `_since` |
| Mapper | FHIR R4 → DTO interno; sin reglas de negocio Bioenlace |
| Resolver | Schedule → PES vía catálogo + fallback compuesto |
| Dominio | Alta turno espejo, estados, `pes_resolution_trust` |

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

## CUIL en alta PES

`Persona.cuil` obligatorio al crear PES clínico (excepción: servicio `AdminEfector`). Mejora matching `Practitioner` inbound y export FHIR saliente.
