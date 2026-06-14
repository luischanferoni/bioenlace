# Intents — operaciones / flows (permiso assignable)

Manifiestos YAML de flujos conversacionales. Cada intent declara:

- `permission` (greenfield) o `rbac_route` (legacy webvimark)
- `subintents` con pasos `open_ui` (heredan permiso del intent)
- `flow_submit` para cierre / mutación

Convención de nombre: `<dominio>.<operacion>-flow.yaml` o sin sufijo `-flow` si es operación atómica.

Los pasos `open_ui` **no** tienen permiso propio en admin: se autorizan vía el intent padre (`FlowStepAccessService`).
