# Fase 06 — Capas PHP en BC + desarmar Integrations

**Estado: hecha** (código ACL movido; docs arquitectura globales → fase 07).

## Hecho

- `NavSisse` / `NavSisseHigh` → `Platform/Ui/Widgets/Sisse/`
- `*Agent` Clinical/Scheduling (+ `IntegrationRetryAgent`) → `Application/Agent/`
- Callers con `use` / FQCN actualizados
- `BoundedContextLayerShapeTest`: sin agents bajo `Service/`; sin `Domain/*/metadata/*.yaml`
- ACL → `BC/Infrastructure/External/`:
  - Person: Identity (`DiditClient`), Mpi
  - Clinical: Laboratory, Prescription, ClinicalHistory
  - Scheduling: FHIR inbound/outbound (ex `Integrations/Scheduling`)
- `Domain/Integrations/` queda solo con README de redirección

## Siguiente

Fase 07: actualizar `common-components.md`, `Domain/README.md`, docs producto/planes que aún citan `Domain/Integrations/…`.
