# Fase 3 — Metadata, UI JSON y tests con la misma forma

## Objetivo

Aplicar la invariante a las tres capas que quedan y borrar los mapas que sustituían la forma faltante.

## Tareas

### 3.1 Metadata

- [ ] `common/metadata/bioenlace/platform/` para lo que hoy es plataforma: `assistant/`, `agents/`, `ai/`, `permission/`, `ui/`
- [ ] Dominios quedan donde están: `clinical/`, `organization/`, `person/`, `scheduling/`, `terminology/`, `integrations/`
- [ ] Mover los intents del asistente al dominio del que hablan: `metadata/bioenlace/<dominio>/intents/<archivo>.yaml`
- [ ] Actualizar `ProductMetadataPaths` e `IntentSchemaPaths` (los paths salen de una sola convención, no de constantes por archivo)

### 3.2 UI JSON

- [ ] `views/json/persona/` → `views/json/person/`
- [ ] `views/json/core/` + `views/json/common/` → `views/json/platform/`
- [ ] `views/json/scheduling/servicios/` y `views/json/scheduling/servicio-teleconsulta/` → `organization/` (decisión 1 de Fase 1)
- [ ] Verificar que los 98 descriptores resuelven

### 3.3 Derivar el dominio y borrar los mapas

- [ ] Derivar `entidad → dominio` del namespace del controller (posible desde Fase 1)
- [ ] Borrar `UiJsonDomainMetadata::ENTITY_DOMAINS` (24 entradas)
- [ ] Borrar `CLINICAL_PREFIX`, la rama especial de `UiJsonDomain::parseActionId()` y las dos regex de `parseApiV1UiRoute()`
- [ ] `product-registries.php`: fragmentos por dominio, prefijo derivado de la carpeta

### 3.4 Tests

- [ ] `tests/unit/`: dominios por un lado, `platform/` por otro (`assistant`, `ai`, `api`, `auth`, `core`, `infra`, `permission`, `ui`, `costos` → `platform/…`)
- [ ] Mantener `_bootstrap.php` y suites donde están

## Fuera de esta fase

- Catálogos derivados (Fase 4).
- Tocar el cuerpo de prompts.

## Criterios de aceptación

- [ ] Primer nivel de `metadata/bioenlace/`, `views/json/` y `tests/unit/` es dominio o `platform`, sin excepciones no documentadas
- [ ] `UiJsonDomainMetadata` ya no tiene mapas de entidad ni prefijo especial
- [ ] Descriptores UI y flows del asistente resuelven; smoke de QA verde

## PR sugerido

`refactor(metadata): forma dominio/plataforma en metadata, ui json y tests`
