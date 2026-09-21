# Fase 04 — Person: Registry + módulos existentes

**Estado:** pendiente

## Objetivo

Dejar de tener un `Application/` catch-all en la raíz de Person; consolidar capacidades.

## Reshape

```text
Person/
  Registry/            # o Identidad/ — ex Application raíz (Flows, Seed, services)
  Representation/      # alinear Application a roles CA
  Ventanilla/          # alinear
  Assistant/
  DataAccess/
```

## Checklist

- [ ] Decidir nombre del módulo raíz (`Registry` vs `Identidad`) con dueño de admisión/ventanilla
- [ ] Mover `Person/Application/*` → módulo elegido
- [ ] Aplanar Application en Representation y Ventanilla
- [ ] Sufijos transversales
- [ ] Enlace con plan `admision-identidad-ventanilla` (no duplicar producto; solo packaging)

## Cuidado

Cross-deps Representation ↔ Registry: dueño de cada tipo; sin `Person/Shared/`.
