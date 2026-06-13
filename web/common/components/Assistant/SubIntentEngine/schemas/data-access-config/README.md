# Data Access — configuración declarativa

Catálogo de **permisos por atributo** (read / info / edit) y consultas staff, junto a intents `data-access.*`.

## Permisos

- Unidad de grant: **`Entidad.atributo.read`**, **`.info`**, **`.edit`** (bloque `attributes:` en YAML).
- **`groups`**: solo presentación (agrupar chips/bloques en el asistente); no son permisos.
- Mutaciones complejas: intents en `schemas/intents/update/` — no duplicar esos campos en `edit.attributes`.

Ver también: [`docs/arquitectura/rbac-catalogo-permisos.md`](../../../../../../docs/arquitectura/rbac-catalogo-permisos.md).

## Estructura

| Archivo | Contenido |
|---------|-----------|
| `manifest.yaml` | Versión global y `filter_synonyms` |
| `{Entidad}.yaml` | `entity`, `model`; opcionalmente `info_list`, `edit`, `ui_json_source` |

Una entidad YAML = un modelo de dominio (p. ej. `ProfesionalEfectorServicioAgenda`, no mezclar agenda dentro de `ProfesionalEfectorServicio`).

- **Permisos por rol**: BD `data_access_role_grant` (backend **Permisos por atributo**).
- **Campos de formulario por grupo**: BD `data_access_attribute_field` (clave `Entidad.grupo`).

## Convenciones

- **`groups`** (opcional, legacy): solo si hace falta declarar atributos de modelo para filtros/métricas. Los grants y campos de formulario viven en BD.
- **`ui_json_source`** (nivel entidad): enlace explícito entidad ↔ `views/json/.../*.json` (source of truth de widgets y `field_meta`).
- **`keywords`**: vocabulario NL por flujo/atributo (el verbo lo resuelve el intent).
- **`info_list`**: consultas staff info/listar allowlisted (`query`, `output`, `presentation_handler`). No usar `metrics` (nombre legacy).
- **`edit`**: flujo `data-access.editar`; **flow id = `entity`** del archivo. Atributos editables en `edit.attributes` (plano, sin `groups` ni `aspects` en YAML).
- **Atributos con `ui_action`**: `open_ui` + `fields` implícito (el atributo) + `ui_flow.impact_preview_policy` (`never` | `when_existing_agenda` | `always`).
- **Atributos sin `ui_action`**: edición escalar vía `attribute_group` + campos en BD.

## Validación

```bash
php yii data-access-catalog/check
```

Comprueba: modelos AR, atributos YAML, `edit.attributes` ↔ grupos, ui_json existente, campos BD vs JSON.

También: `php yii ui-json-templates/check` para contrato UI JSON.

## Intents relacionados

Los canales staff **info / listar / editar** se descubren vía `DataAccessUiActionCatalog` (sin YAML de flow en `intents/`).

Agenda staff: `data-access.editar` → flujo `ProfesionalEfectorServicioAgenda` → atributo (p. ej. `weekly_scheduler_widget`, `formas_atencion`) → `profesional-agenda.configurar-agenda` (paso impacto si ya hay agenda y se tocan campos de grilla).
