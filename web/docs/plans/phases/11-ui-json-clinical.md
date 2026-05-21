# Fase 11 — UI JSON clínica + reorganización `views/json`

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md), [Fase 6](./06-orders-medication-practice.md)  
**Estado:** hecho

## Objetivo

Reorganizar plantillas UI JSON bajo dominios y crear descriptores clínicos servidos por `UiScreenService`.

## Reorganización carpetas

```text
frontend/modules/api/v1/views/json/
  scheduling/          # turnos, profesional-agenda, efectores, servicios
  clinical/            # care-plan, encounter
  persona/             # persona/buscar-para-asistente
  organization/        # profesional-efector-servicio
  README.md
```

Resolución: `UiJsonDomain` + `UiDefinitionTemplateManager::resolveTemplateAbsolutePath()` (dominio primero, fallback `{entidad}/{accion}.json` legacy).

## Tareas

- [x] `UiDefinitionTemplateManager` / `UiJsonDomain`: paths por subcarpeta de dominio.
- [x] Controllers: sin cambio de `handleScreen('turnos', …)` — rutas HTTP estables.
- [x] Descriptores clínicos: `ver-tratamiento-paciente`, `listar-ordenes-activas`.
- [x] Endpoints: `CarePlanController::actionVerTratamientoPaciente`, `EncounterController::actionListarOrdenesActivas`.
- [x] RBAC: `m260521_100007_api_clinical_ui_json_rbac`.
- [x] Contrato actualizado: [UI_JSON_DESCRIPTOR_CONTRACT.md](../../asistente/UI_JSON_DESCRIPTOR_CONTRACT.md).

## Compatibilidad rutas HTTP

- `/api/v1/turnos/*` y resto scheduling **sin cambio**.
- Clínica UI: `/api/v1/clinical/care-plan/ver-tratamiento-paciente`, `/api/v1/clinical/encounter/listar-ordenes-activas`.

## Definition of Done

- [x] Templates scheduling/persona/organization movidos bajo dominio; `crear-como-paciente` en `scheduling/turnos/`.
- [x] Al menos un descriptor clínico piloto servido por `UiScreenService` (dos: care plan paciente + órdenes encounter).

## Siguiente fase

[Fase 12 — Yii web](./12-yii-web.md)
