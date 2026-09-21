# Fase 02 — Organization: módulo-primero

**Estado:** hecho (reshape mecánico + FQCN; Billing/Entitlement bajo Efector)  
**Depende de:** fase 01 (opcional en paralelo si no hay solapamiento de FQCN)

## Objetivo

Pasar de layer-first + capacidades bajo `Application/` a **módulos de capacidad** con el mismo eje que Clinical.

## Reshape

```text
Organization/
  Efector/             # + Billing + Entitlement + Seed
  Servicio/
  Pes/                 # ProfesionalEfectorServicio + ProfesionalHorario + Presentation
  SesionOperativa/
  Assistant/
  DataAccess/
```

Cada módulo:

```text
<Modulo>/
  Application/{UseCase,Service,Presentation,Authorization,Flows,Seed?}
  Domain/{Model,Catalog,Policy,Port,…}
  Infrastructure/{Persistence,External,…}
```

## Checklist

- [x] Cerrar decisión Billing/Entitlement → bajo `Efector/`
- [x] Matriz move: `Application/Efectores/*` → `Efector/Application/…`
- [x] Idem Servicios, PES, SesionOperativa, Authorization/Flows/Presentation (repartir por dueño)
- [x] Sufijos transversales en clases movidas (`*Formatter`→`*Presenter`, `AgendaSlotEngine`→`AgendaSlotService`)
- [x] Console seeds / `ProductMetadataPaths` (discovery módulo-primero ya soportado) / registries
- [x] README `Organization/` + test de forma del BC

## Riesgo alto

Muchos entrypoints (sesión operativa, set-session). Preferir **moves mecánicos** + un PR por módulo hijo.
