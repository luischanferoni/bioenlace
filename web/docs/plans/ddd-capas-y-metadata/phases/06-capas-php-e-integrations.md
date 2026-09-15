# Fase 06 — Capas PHP en BC + desarmar Integrations

**Estado: parcial.**

## Hecho

- `NavSisse` / `NavSisseHigh` → `Platform/Ui/Widgets/Sisse/` (fuera de Domain)
- `IntegrationRetryAgent` → `Clinical/Application/Agent/`
- Eliminados `Integrations/Sisse` e `Integrations/Service` vacíos

## Pendiente

- Mover resto `Integrations/<Sistema>/` → `BC/Infrastructure/External/`
- Mover `*Agent` de `Clinical|Scheduling/.../Service/` → `Application/Agent/`
- Endurecer `BoundedContextLayerShapeTest` (hoy clinical/scheduling en allowlist pending)
