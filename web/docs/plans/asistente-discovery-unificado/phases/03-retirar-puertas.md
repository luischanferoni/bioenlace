# Fase 03 — Retirar puertas (direct-doors / aspectos)

## Objetivo

Eliminar el uso de `direct-doors` como índice NL. Cobrir casos con intent rico o artículo BD.

## Hecho

| Antes (direct-doors) | Destino |
|----------------------|---------|
| `llegar-tarde-*` | Tags en `turnos.consultar-politica-autogestion-flow` + aspectos vía área `scheduling` |
| `articulo-representacion` | Artículo BD `representacion` vía `DiscoveryIndex` |
| `fuera-his-*` | Hint/tag `fuera_his` → `FueraDeHisHandler` (copy en `smart-catalog-routing.yaml`) |

- `direct-doors.yaml` → `entries: []`
- `SmartCatalogRoutingService` ya no llama a `SmartCatalogMatchService`; usa `DiscoveryIndex`
- `PreprocessTagVocabularyCatalog` deriva tags de `StateTagIndex` + extras PHP (no del catálogo de puertas)
- Tests de match/routing/QA conversacional actualizados

## Criterio de hecho

- [x] Hot path de routing sin entradas de puertas
- [x] Llegar tarde → intent política + `aspect:site.appointment.policies`
- [x] Fuera HIS por hint/tag
- [ ] Smoke QA manual representación con BD seed

## Siguiente

Fase 04: docs producto/ADR y cierre del plan.
