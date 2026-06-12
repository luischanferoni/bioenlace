# Data Access — configuración declarativa

Catálogo staff (grupos de atributos, métricas, edición dispersa) junto a los intents `data-access.*`.

## Estructura

| Archivo | Contenido |
|---------|-----------|
| `manifest.yaml` | Versión global y `filter_synonyms` |
| `{Entidad}.yaml` | `groups` de la entidad; opcionalmente `metrics` y `edit_surfaces` |

- **Permisos por rol**: BD `data_access_role_grant` (backend **Permisos por atributo**).
- **Campos de formulario por grupo**: BD `data_access_attribute_field` (misma clave `Entidad.grupo`; seed canónico desde `configurar-agenda.json` para agenda).

## Convenciones

- **`groups` en YAML**: registro del grupo (clave para grants y métricas). Listas simples de nombres (`asignacion`) solo como fallback legacy; formularios ricos van en BD.
- **`keywords`**: vocabulario NL para descubrimiento (métricas y superficies). El verbo (listar, contar, editar) lo resuelve el intent, no el catálogo.
- **`metrics`**: consultas allowlisted (`query`, `output`, `presentation_handler`).
- **`edit_surfaces`**: flujo entidad → sujeto → dato → formulario (`data-access.editar`).

## Intents relacionados

- `intents/data-access.info.yaml`
- `intents/data-access.listar.yaml`
- `intents/data-access.editar.yaml`
