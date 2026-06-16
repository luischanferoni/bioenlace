# Fase 1 — Fundación metadata e integridad

## Objetivo

Definir el contrato YAML de intents read/list/edit y adaptar el checker de integridad para el modelo **solo intents**, sin bloquear el repo durante la convivencia con el catálogo legacy.

## Tareas

### 1.1 Esquema metadata

- Documentar en `design.md` (este plan) los bloques obligatorios/opcionales: `operation`, `intent_family`, `domain_operation`, `subject_resolution`, `field_groups`, `fields`
- Decidir ubicación física: `schemas/intents/read/` vs reutilizar `update/` para edits
- Añadir validación en loader de intents (manifest index) sin romper intents create existentes

### 1.2 `intent_family` y aliases

- Extender `intent-aliases.yaml` o reglas de clasificación para familias edit/list
- Checker: miembros de familia con `operation` coherente; sin `intent_id` huérfano

### 1.3 `CatalogIntegrityService`

- Nueva sección: intents read/list/edit ↔ `rbac_route` ↔ `domain_operation`
- Regla **warning** (fase convivencia): grants `Entidad.atributo.*` aún en `auth_item`
- Regla **error** (fase final): grants atributo prohibidos
- Validar `fields` contra modelos AR / UI JSON paths (reutilizar lógica de `data-access-catalog/check` donde aplique)

### 1.4 `IntentManifestIndex` / catálogo asistente

- Indexar `intent_family`, `operation`, campos para admin lectura
- Excluir progresivamente `data-access.*` del índice de descubrimiento cuando exista reemplazo

### 1.5 Tests unitarios

- Integridad: intent piloto válido / inválido
- Familia NL: un candidato vs varios

## Entregables

- [ ] Contrato YAML estable (documentado)
- [ ] `php yii catalog-integrity/check` con reglas nuevas (warnings convivencia)
- [ ] Tests unitarios fase 1
- [ ] Sin cambios aún en asignación admin ni en `AttributePermissionEvaluator` en runtime API

## Dependencias

- Fase 0 cerrada (convenciones de nombres)

## Estado

En progreso (fase 1 iniciada): contrato extendido, `intent-families.yaml`, índice ampliado, checker de integridad y piloto condición laboral.
