# Plan — RBAC unificado por intents

| Campo | Valor |
|-------|--------|
| Slug | `rbac-intents-unificado` |
| Estado | Fase 1–2 completadas; fase 3 pendiente |
| Reemplaza / tensiona con | Catálogo intents + atributos; intents genéricos `data-access.*`; scopes ABAC como dimensión admin |
| Doc estable al cerrar | `arquitectura/rbac-catalogo-permisos.md` + `decisions/` (ADR autorización) |

## Decisiones de producto (cerradas)

| # | Tema | Decisión |
|---|------|----------|
| R1 | Unidad de permiso assignable | Solo **intents** (`intent_id` en `auth_item`), como los flows **create** actuales |
| R2 | Permisos por atributo / grupo | **No**. Los campos editables/listables viven en el **YAML del intent** (o UI JSON referenciado). Admin puede **mostrarlos en solo lectura** |
| R3 | Variación de campos por rol | **Intent distinto** con YAML distinto (ej. staff enfermero vs staff administrativo), no grants atómicos |
| R4 | Contexto operativo (propio / staff / paciente) | **Intent distinto** por variante (ej. `…-propio` vs `…-staff`), no campo «scope» en el rol |
| R5 | Elección «¿mío o de otro?» en asistente | Si el usuario tiene **más de un intent** de la misma familia, el flujo pregunta; textos desde `intent_semantics` / metadata del YAML |
| R6 | Operaciones ver / listar / editar / crear | Misma regla: **permiso = intent**; no canal genérico con sub-permisos por campo |
| R7 | Seguridad sobre recurso concreto | **Políticas de dominio** en persistencia (`domain-operation-policies.yaml` + servicios), independiente del RBAC de intent |
| R8 | Rol paciente por defecto | Se mantiene el patrón actual (`paciente` inyectado); intents paciente asignados a ese rol base |

## Índice

- [overview.md](./overview.md) — problema, estado actual, objetivo
- [design.md](./design.md) — capas, convenciones de intents, admin, integridad, migración
- [phases/00-marco.md](./phases/00-marco.md) — alcance, fuera de alcance, piloto
- [phases/01-fundacion-metadata-integridad.md](./phases/01-fundacion-metadata-integridad.md)
- [phases/02-catalogo-admin-solo-intents.md](./phases/02-catalogo-admin-solo-intents.md)
- [phases/03-migrar-canal-data-access.md](./phases/03-migrar-canal-data-access.md)
- [phases/04-asistente-descubrimiento-familias.md](./phases/04-asistente-descubrimiento-familias.md)
- [phases/05-dominio-api-endpoints.md](./phases/05-dominio-api-endpoints.md)
- [phases/06-retiro-legacy-y-documentacion.md](./phases/06-retiro-legacy-y-documentacion.md)

## Piloto de referencia

Condición laboral + listado PES en efector: ver fase 05 y `phases/00-marco.md`.

## Al cerrar el programa

1. Actualizar `web/docs/arquitectura/rbac-catalogo-permisos.md`.
2. ADR en `web/docs/decisions/` si aplica.
3. Borrar `plans/rbac-intents-unificado/` (`plans/README.md`).
