# Fase 02 — Organization: módulo-primero

**Estado:** pendiente  
**Depende de:** fase 01 (opcional en paralelo si no hay solapamiento de FQCN)

## Objetivo

Pasar de layer-first + capacidades bajo `Application/` a **módulos de capacidad** con el mismo eje que Clinical.

## Reshape

```text
Organization/
  Efector/
  Servicio/
  Pes/                 # ProfesionalEfectorServicio + ProfesionalHorario
  SesionOperativa/
  # Billing / Entitlement: ver design.md (default bajo Efector/)
  Assistant/
  DataAccess/
```

Cada módulo nuevo:

```text
<Modulo>/
  Application/{UseCase,Service,Presentation,Authorization,Flows,Seed?}
  Domain/{Model,Catalog,Policy,Port,…}
  Infrastructure/{Persistence,External,…}
```

## Checklist

- [ ] Cerrar decisión Billing/Entitlement (módulo propio vs bajo Efector)
- [ ] Matriz move: `Application/Efectores/*` → `Efector/Application/…`
- [ ] Idem Servicios, PES, SesionOperativa, Authorization/Flows/Presentation (repartir por dueño)
- [ ] Sufijos transversales en clases movidas
- [ ] Console seeds / `ProductMetadataPaths` / permisos API
- [ ] README `Organization/` + test de forma del BC

## Riesgo alto

Muchos entrypoints (sesión operativa, set-session). Preferir **moves mecánicos** + un PR por módulo hijo.
