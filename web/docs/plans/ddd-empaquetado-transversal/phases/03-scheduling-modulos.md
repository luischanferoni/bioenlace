# Fase 03 — Scheduling: módulos + BehaviorProfile

**Estado:** pendiente  
**Depende de:** design.md (nombres Agenda / BehaviorProfile)

## Objetivo

Eliminar capacidades bajo `Application/` y alinear `Quirofano/` / nuevo `Agenda/` / `BehaviorProfile/`.

## Reshape

```text
Scheduling/
  Agenda/              # turnos, agents, authorization de agenda, presentation
  Quirofano/           # ya existe — Application solo roles CA
  BehaviorProfile/     # sacar de Application/BehaviorProfile
  Home/
  Assistant/
```

## Checklist

- [ ] Inventario `Scheduling/Application/*` (Agents, Authorization, Flows, Presentation, BehaviorProfile)
- [ ] Mover BehaviorProfile a módulo L1 del BC
- [ ] Agrupar el resto en `Agenda/` (o nombre de producto acordado: `Turno/` si el lenguaje lo pide)
- [ ] Alinear Quirofano Application
- [ ] Sufijos: agents ya `*Agent` OK; renombrar outliers
- [ ] README + intents discovery

## Nota de naming

Si el lenguaje ubícuo es “turno” más que “agenda”, el módulo puede llamarse `Turno/` — decidir antes del primer move y no dejar alias.
