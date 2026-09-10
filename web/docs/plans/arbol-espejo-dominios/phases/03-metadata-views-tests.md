# Fase 3 — Metadata, UI JSON y tests con la misma forma

## Objetivo

Aplicar la invariante a las tres capas que quedan y borrar los mapas que sustituían la forma faltante.

## Cómo se decidió

- **UI JSON** ya estaba espejado (`views/json/<dominio>/<entidad>/`); `UiJsonDomainIndex` deriva entidad→dominio del árbol (reemplaza `ENTITY_DOMAINS`).
- **Intents**: dominio = quien **persiste** el resultado (`design.md` §9). `atencion.necesito-atencion` → `clinical`; DataAccess y queja → `platform`.
- **Tests**: lo transversal baja un nivel bajo `platform/` (misma posición que un dominio).

## Tareas

### 3.1 Metadata

- [x] `common/metadata/bioenlace/platform/` para `assistant/`, `agents/`, `ai/`, `permission/`, `ui/`
- [x] Dominios con intents: `clinical/`, `organization/`, `person/`, `scheduling/` (+ `platform/intents/`)
- [x] 55 intents movidos a `<dominio>/intents/{create,read,update,delete}/`
- [x] `ProductMetadataPaths` apunta a `platform/…`; `IntentSchemaPaths` descubre todos los `*/intents/`
- [x] `IntentSchemaPaths::domainForIntentId()` / `domainFromPath()` (base de Fase 5)

### 3.2 UI JSON

- [x] `views/json/persona/` → `person/` (ya estaba)
- [x] `views/json/core/` + `common/` → `platform/` (ya estaba)
- [x] `servicios` / `servicio-teleconsulta` bajo `organization/` (ya estaba)
- [x] `UiJsonDomainIndex` indexa el árbol; alias técnicos (`care-plans`→`care-plan`) en el loader

### 3.3 Derivar el dominio y borrar los mapas

- [x] Dominio de entidad UI derivado del árbol (`UiJsonDomainIndex`)
- [x] `UiJsonDomainMetadata` eliminado; test → `UiJsonDomainIndexTest`
- [x] `parseActionId` / `parseApiV1UiRoute` usan `isDomain()` del índice (sin `CLINICAL_PREFIX`)
- [ ] `product-registries.php` en fragmentos por dominio: **diferido** — los ids ya llevan prefijo de dominio; partir el archivo no cambia la forma del árbol y los handlers sin prefijo (`data_access.*`, secciones del home) no encajan en un esquema rígido. Queda como mejora de higiene, no bloquea 4–5.

### 3.4 Tests

- [x] `tests/unit/`: solo dominios + `platform/` (`agent`, `ai`, `api`, `assistant`, `auth`, `core`, `costos`, `infra`, `permission`, `ui` bajo `platform/`)
- [x] Namespaces reescritos; `_bootstrap.php` / suites intactos

## Fuera de esta fase

- Catálogos derivados (Fase 4).
- Tocar el cuerpo de prompts.
- Fragmentar `product-registries.php`.

## Criterios de aceptación

- [x] Primer nivel de `metadata/bioenlace/`, `views/json/` y `tests/unit/` es dominio o `platform`
- [x] No queda `UiJsonDomainMetadata` ni mapa `ENTITY_DOMAINS`
- [ ] Descriptores UI y flows del asistente resuelven; smoke de QA verde (pendiente del entorno / usuario)

## Verificación aplicada

- 55 intents descubiertos; sample por dominio (`scheduling`/`clinical`/`organization`/`person`/`platform`) con categoría CRUD correcta.
- `ProductMetadataPaths`: guide, preprocess, home-panel, agents, permission resuelven bajo `platform/`.
- `UiJsonDomainIndex`: `turnos`→scheduling, `care-plans`→clinical (vía alias), `person-representation`→person.

## PR sugerido

`refactor(metadata): forma dominio/plataforma en metadata, ui json y tests`
