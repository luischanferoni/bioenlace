# Data Access — configuración declarativa

Catálogo staff (grupos de atributos, métricas, edición dispersa) junto a los intents `data-access.*`.

## Estructura

| Archivo | Contenido |
|---------|-----------|
| `manifest.yaml` | Versión global y `filter_synonyms` |
| `{Entidad}.yaml` | `entity`, `model`, `groups`; opcionalmente `info_list` (consultas info/listar) y `edit_surfaces` |

Una entidad YAML = un modelo de dominio (p. ej. `ProfesionalEfectorServicioAgenda`, no mezclar agenda dentro de `ProfesionalEfectorServicio`).

- **Permisos por rol**: BD `data_access_role_grant` (backend **Permisos por atributo**).
- **Campos de formulario por grupo**: BD `data_access_attribute_field` (clave `Entidad.grupo`).

## Convenciones

- **`groups`**: registro del grupo + `attributes` alineados al modelo AR. `version_attributes` si el dato vive en tabla de versiones.
- **`ui_json_source`**: enlace explícito grupo ↔ `views/json/.../*.json` (source of truth de widgets y `field_meta`).
- **`keywords`**: vocabulario NL por superficie/aspecto (el verbo lo resuelve el intent).
- **`info_list`**: consultas staff info/listar allowlisted (`query`, `output`, `presentation_handler`). No usar `metrics` (nombre legacy).
- **`edit_surfaces`**: flujo entidad → sujeto → dato → formulario (`data-access.editar`).
- **Aspectos `field_group`**: `fields: [nombre, apellido, …]` — edición por atributos, no por grupo entero.
- **Aspectos `open_ui`**: `ui_action` + `fields` opcional (subconjunto del JSON) + `ui_flow.impact_preview_policy` (`never` | `when_existing_agenda` | `always`).

## Validación

```bash
php yii data-access-catalog/check
```

Comprueba: modelos AR, atributos YAML, aspectos ↔ grupos, ui_json existente, campos BD vs JSON.

También: `php yii ui-json-templates/check` para contrato UI JSON.

## Intents relacionados

Los canales staff **info / listar / editar** se descubren vía `DataAccessUiActionCatalog` (sin YAML de flow en `intents/`).

Agenda staff: `data-access.editar` → superficie `agenda_profesional_en_efector` → aspectos `agenda_grilla` / `agenda_modalidad` → `profesional-agenda.configurar-agenda` (paso impacto si ya hay agenda y se tocan campos de grilla).
