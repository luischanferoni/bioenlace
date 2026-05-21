# Fase 5 — Ciclo de vida CarePlan (crónicos, programas, alta)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md)  
**Estado:** hecho

## Objetivo

Implementar reglas de negocio para **cuándo** un CarePlan está activo, pausado o terminado, incluyendo internación, ambulatorio, crónicos y programas.

## Categorías (`care_plan.category`) — lista cerrada v1

Ver [CARE_PLAN_CATEGORIES.md](../CARE_PLAN_CATEGORIES.md). Validación en código: `CarePlanCategory::isValid()`.

## Matriz de transición (estado FHIR)

| Desde | Hacia permitido |
|-------|-----------------|
| `draft` | `active`, `revoked`, `entered-in-error` |
| `active` | `on-hold`, `completed`, `revoked`, `entered-in-error` |
| `on-hold` | `active`, `completed`, `revoked` |
| `completed` | — (terminal; API rechaza mutaciones) |
| `revoked` | — (terminal) |

Implementación: `CarePlanStatus::canTransition()` + `CarePlanService::assertMutable()`.

## Reglas por categoría al cerrar encounter

| Categoría | ¿Se completa al `EncounterLifecycleService::close()`? |
|-----------|--------------------------------------------------------|
| `acute-ambulatory`, `postoperative`, `other`, … | Sí |
| `chronic`, `program`, `inpatient`, `palliative`, `preventive` | No |

`continue_treatment=true` en opciones de cierre crea plan `chronic` y revoca crónicos activos previos.

## Internación

| Evento | Servicio | Efecto |
|--------|----------|--------|
| Ingreso | `CarePlanLifecycleService::onInternacionAdmission()` | `EpisodeOfCare` active + `CarePlan` `inpatient` active |
| Alta | `CarePlanLifecycleService::completeOnDischarge()` | Episode `finished`, planes inpatient `completed`, encounters IMP `finished` |

Hooks: `InternacionController` (ingreso), `SegNivelInternacionRepository::doExternacion()` (alta).

## Programa (sesiones)

Metadatos en `care_plan.description` (JSON): `CarePlanProgramMeta` (`occurrenceTotal`, `occurrenceCount`).  
`recordProgramSession()` incrementa contador y auto-`completed` si se agotan sesiones o `period_end` venció.

## Servicios (código)

| Clase | Rol |
|-------|-----|
| `CarePlanLifecycleService` | Orquestación ingreso/alta/cierre encounter, crónico, programa |
| `CarePlanService` | CRUD estado (`activate`, `hold`, `complete`, `revoke`) |
| `EpisodeOfCareService` | Episodio ligado a `seg_nivel_internacion` |
| `EncounterLifecycleService::close()` | Finaliza encounter + reglas care plan |

## API

| Método | Ruta |
|--------|------|
| POST | `/api/v1/clinical/care-plans/<id>/hold` |
| POST | `/api/v1/clinical/care-plans/<id>/activate` |
| POST | `/api/v1/clinical/care-plans/<id>/complete` |
| POST | `/api/v1/clinical/care-plans/<id>/revoke` |

Errores 400 si el plan está `completed` / `revoked`.

`GET care-plans/active`: `active` + `on-hold`; excluye `completed` y `revoked`.

## Tests

- `common/tests/unit/clinical/CarePlanLifecycleServiceTest.php`
- `common/tests/unit/clinical/CarePlanServiceTest.php`

## Migraciones RBAC

- `m260521_100005_api_clinical_care_plan_hold_activate_rbac.php`

## Siguiente fase

[Fase 6 — Órdenes](./06-orders-medication-practice.md)
