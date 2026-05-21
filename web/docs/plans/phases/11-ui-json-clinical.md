# Fase 11 — UI JSON clínica + reorganización `views/json`

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md), [Fase 6](./06-orders-medication-practice.md)  
**Estado:** pendiente

## Objetivo

Reorganizar plantillas UI JSON bajo dominios y crear descriptores clínicos donde la captura use `UiScreenService`.

## Reorganización carpetas

```text
frontend/modules/api/v1/views/json/
  scheduling/          # mover turnos/, profesional-agenda/, efectores/ (rutas en JSON internas)
  clinical/            # nuevos: encounter/, care-plan/, medication-request/, …
  organization/        # PES, servicios (opcional)
  persona/             # buscar-para-asistente, etc.
```

## Tareas

- [ ] Actualizar `UiDefinitionTemplateManager` paths si usan rutas `entity/action` → reflejar subcarpetas o mantener convención `clinical/encounter/guardar.json`.
- [ ] Controllers que llaman `handleScreen('turnos', ...)` → `handleScreen` con path actualizado o alias en manager.
- [ ] Descriptores para listados/confirmación de órdenes si el producto usa mini-UI nativa vía JSON.
- [ ] Documentar en [../../asistente/UI_JSON_DESCRIPTOR_CONTRACT.md](../../asistente/UI_JSON_DESCRIPTOR_CONTRACT.md).

## Compatibilidad rutas HTTP

- Rutas públicas `/api/v1/turnos/*` **sin cambio** aunque el JSON viva en `scheduling/`.

## Definition of Done

- Build API encuentra todos los JSON (grep + smoke `crear-como-paciente`).
- Al menos un descriptor clínico piloto servido por `UiScreenService`.

## Siguiente fase

[Fase 12 — Yii web](./12-yii-web.md)
