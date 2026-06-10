# Fase 1 — Fundación RBAC + discovery + API picker

**Estado:** implementada

## Entregables

- [x] Plan documentado
- [x] `QueryOperation::WRITE`
- [x] `edit_surfaces` piloto (`profesional_en_efector`)
- [x] `EditSurfaceAuthorizationService` + `DataAccessEditDiscoveryService`
- [x] `DataAccessEditUiService` (picker superficie / aspectos / enlace listar)
- [x] `EditarController`, rutas, migración RBAC `/api/editar`
- [x] Intent `data-access.editar.yaml`, catálogo, hydrator
- [x] Tests unitarios grants `write`

## Fuera de fase

- Formulario parcial, confirmación, `MutationExecutor` (Fase 2–3)
- Preprocess chat “editar/modificar” (Fase 4)
- Deprecar `agenda.editar-agenda-flow` (Fase 5)
