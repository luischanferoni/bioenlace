# Fase 03 — Scheduling: módulos + BehaviorProfile

**Estado:** hecho (Agenda + BehaviorProfile + Quirofano alineado; módulo `Agenda/` como lenguaje del plan)  
**Depende de:** design.md (nombres Agenda / BehaviorProfile)

## Objetivo

Eliminar capacidades bajo `Application/` y alinear `Quirofano/` / nuevo `Agenda/` / `BehaviorProfile/`.

## Reshape

```text
Scheduling/
  Agenda/              # turnos, agents, authorization de agenda, presentation, infra FHIR
  Quirofano/           # Application solo roles CA
  BehaviorProfile/     # ex Application/BehaviorProfile
  Home/
  Assistant/
```

## Checklist

- [x] Inventario `Scheduling/Application/*` (Agents, Authorization, Flows, Presentation, BehaviorProfile)
- [x] Mover BehaviorProfile a módulo L1 del BC
- [x] Agrupar el resto en `Agenda/`
- [x] Alinear Quirofano Application (`Service/`)
- [x] Sufijos outliers (`*Notifier`/`*Builder`/`*Scheduler`/`*Finder`/`*Calculator`/`*Reader`)
- [x] README + intents discovery (`Agenda/Application/Flows/intents`) + shape tests
