# Plan — DataAccess edición dispersa (sparse edit)

| Campo | Valor |
|-------|--------|
| Slug | `data-access-edicion-sparse` |
| Estado | En ejecución — Fase 4 (asistente) |
| Origen | Conversación producto (extensión de `info` / `listar`) |
| Objetivo | Un intent `data-access.editar` + metadata `edit_surfaces` con permisos por grupo (`write`), picker de superficie/aspecto/sujeto, confirmación obligatoria |

## Índice

- [overview.md](./overview.md)
- [design.md](./design.md)
- [phases/01-fundacion-rbac-discovery.md](./phases/01-fundacion-rbac-discovery.md) — **cerrada**
- [phases/02-formulario-parcial-confirmar.md](./phases/02-formulario-parcial-confirmar.md) — **cerrada**
- [phases/03-mutation-executor-handlers.md](./phases/03-mutation-executor-handlers.md) — **cerrada**
- [phases/04-asistente-preprocess.md](./phases/04-asistente-preprocess.md)
- [phases/05-migrar-flows-agenda.md](./phases/05-migrar-flows-agenda.md)

## Código

| Área | Ubicación |
|------|-----------|
| Operación `write` | `QueryOperation::WRITE` |
| Superficies YAML | `attribute_groups_v1.yaml` → `edit_surfaces` |
| Autorización | `EditSurfaceAuthorizationService` |
| Discovery NL | `DataAccessEditDiscoveryService` |
| Formulario / confirm | `Edit/EditSparseSubjectLoader`, `EditSparseFieldBuilder`, `EditSparseConfirmPresenter` |
| Mutación | `MutationExecutor`, `EditMutationAuthorizationService`, `EditMutationRegistry`, `PersonaIdentidadBasicaUpdateService` |
| API | `GET\|POST /api/v1/editar`, `EditarController` (`step=form\|confirm\|apply`) |
| Intent | `data-access.editar.yaml` |

## Al cerrar el programa

Volcar narrativa estable en `common/components/Core/DataAccess/README.md` y borrar esta carpeta.
