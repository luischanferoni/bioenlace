# Atajos (`GET /api/v1/acciones/comunes`) — flows + RBAC + categorías

Este endpoint alimenta el panel “Atajos” de los clientes (web SPA y Flutter) y devuelve **solo flows conversacionales** (intents YAML).

## Fuente de verdad

- Intents/flows: `common/components/Assistant/SubIntentEngine/schemas/intents/*.yaml`
- Catálogo YAML: `common/components/Assistant/Catalog/YamlIntentCatalogService.php`
- Atajos + agrupación + RBAC: `common/components/Services/Actions/CommonActionsService.php`
- Endpoint: `frontend/modules/api/v1/controllers/AccionesController.php`

## Contrato (respuesta)

`GET /api/v1/acciones/comunes`

```json
{
  "success": true,
  "actions": [
    {
      "name": "Editar agenda",
      "description": "...",
      "action_id": "agenda.editar-agenda-flow",
      "client_open": { "kind": "intent", "intent_id": "agenda.editar-agenda-flow" },
      "client_interaction": "intent_flow"
    }
  ],
  "categories": [
    {
      "id": "rrhh_agenda",
      "titulo": "Recursos Humanos, Agenda y Condición laboral",
      "actions": [ /* mismas actions, agrupadas */ ]
    }
  ]
}
```

- `categories` es el shape recomendado para render de panel/menú.
- `actions` es un flatten de compatibilidad (clientes legacy que todavía renderizan grilla plana).

## Web SPA (render)

- Vista: `frontend/views/site/asistente.php` (botón “Atajos” + panel inline).
- JS: `frontend/web/js/spa-home.js`:
  - consume `categories` y renderiza acorde
  - al click de un atajo inicia el flow de forma determinista enviando `intent_id` vía `/api/v1/asistente/enviar`

## RBAC (cómo se filtra)

Cada YAML de intent debe declarar `rbac_route`:

```yaml
intent_id: agenda.editar-agenda-flow
rbac_route: "/api/agenda/editar-agenda-flow"
```

El backend filtra los flows con:

- `ActionMappingService::userIdCanAccessRoute($userId, $rbac_route)`

Regla de seguridad: si un flow no declara `rbac_route`, **no se lista** en atajos.

## Categorías (agrupación manual)

La agrupación se define manualmente (hardcode) en backend para evitar lógica de UI duplicada:

- `CommonActionsService::flowCategoriesDefinition()`

Los clientes no deben hardcodear categorías; solo renderizan lo devuelto.

## Orden

- Las acciones dentro de una categoría se ordenan alfabéticamente por `name` (case-insensitive).

