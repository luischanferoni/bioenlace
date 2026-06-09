# Plan — Cohortes, asistencia dinámica y batch IA

| Campo | Valor |
|-------|--------|
| Slug | `cohortes-asistencia-batch` |
| Estado | En ejecución — Fase 3 |
| Origen | [producto/ideas-a-futuro/](../../producto/ideas-a-futuro/README.md) |
| Objetivo | Packs reutilizables por cohorte (~90 % similitud), generación en cola/batch Vertex, runtime sin IA salvo delta |

## Índice

- [overview.md](./overview.md)
- [design.md](./design.md)
- [phases/01-infra-dominio-batch.md](./phases/01-infra-dominio-batch.md) — cerrada
- [phases/02-asistencia-pre-consulta.md](./phases/02-asistencia-pre-consulta.md) — **implementada** (falta UI asistente/móvil → Fase 4)
- [phases/03-seguimiento-post-educacion.md](./phases/03-seguimiento-post-educacion.md) — **implementada**
- [phases/04-api-clientes-asistente.md](./phases/04-api-clientes-asistente.md)
- [phases/05-vertex-batch-produccion.md](./phases/05-vertex-batch-produccion.md)

## Código Fase 1 (punto de partida)

| Área | Ubicación |
|------|-----------|
| Dominio cohorte + packs | `common/components/Clinical/CareCohort/` |
| Tablas | migración `m260613_100000_care_cohort_packs` |
| Cron / consola | `console/controllers/CarePackController.php` |
| Hook cierre encounter | `EncounterLifecycleService::finalize` |
| Hook encounter turno | `EncounterLifecycleService::ensureFromTurno` |

## Al cerrar el programa

Volcar narrativa estable a `producto/asistencia-cohortes.md` y borrar esta carpeta (`plans/README.md`).
