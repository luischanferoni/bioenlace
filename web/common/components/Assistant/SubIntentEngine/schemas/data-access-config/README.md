# Data Access — configuración declarativa

Catálogo staff (grupos de atributos, métricas, edición dispersa) junto a los intents `data-access.*`.

## Estructura

| Archivo | Contenido |
|---------|-----------|
| `manifest.yaml` | Versión global y `filter_synonyms` |
| `{Entidad}.yaml` | `groups` de la entidad; opcionalmente `metrics` y `edit_surfaces` |

Los permisos por rol (**grants**) no viven aquí: se administran en BD (`data_access_role_grant`, pantalla backend **Permisos por atributo**).

## Convenciones

- **`keywords`**: vocabulario NL para descubrimiento (métricas y superficies). El verbo (listar, contar, editar) lo resuelve el intent, no el catálogo.
- **`metrics`**: consultas allowlisted (`query`, `output`, `presentation_handler`).
- **`edit_surfaces`**: flujo entidad → sujeto → dato → formulario (`data-access.editar`).

## Intents relacionados

- `intents/data-access.info.yaml`
- `intents/data-access.listar.yaml`
- `intents/data-access.editar.yaml`
