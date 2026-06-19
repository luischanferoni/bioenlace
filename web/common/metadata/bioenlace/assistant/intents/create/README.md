# Intents — operaciones / flows (permiso assignable)

Manifiestos YAML de flujos conversacionales. Cada intent declara:

- `permission` (greenfield) o `rbac_route` (legacy webvimark)
- `subintents` con pasos `open_ui` (heredan permiso del intent)
- `flow_submit` para cierre / mutación

Convención de nombre: `<dominio>.<operacion>-flow.yaml` o sin sufijo `-flow` si es operación atómica.

Prefijo numérico opcional para orden en disco: `NN-<intent_id>.yaml` (el `intent_id` del YAML no cambia). Ver `update/README.md`.

Los pasos `open_ui` **no** tienen permiso propio en admin: se autorizan vía el intent padre (`FlowStepAccessService`).
