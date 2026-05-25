# UI JSON templates (API v1)

Descriptores servidos por `UiScreenService` / `UiDefinitionTemplateManager`.

## Carpetas por dominio

| Carpeta | Entidades |
|---------|-----------|
| `scheduling/` | `turnos`, `profesional-agenda`, `efectores`, `servicios` |
| `clinical/` | `care-plan`, `encounter`, … |
| `persona/` | `persona` |
| `organization/` | `profesional-efector-servicio` |

Las rutas HTTP públicas **no cambian** (ej. `GET /api/v1/turnos/crear-como-paciente`). Clínica UI: `GET /api/v1/clinical/care-plan/ver-tratamiento-paciente`.

Convención en código: `handleScreen('<entidad>', '<accion>', …)` — el manager resuelve `{dominio}/{entidad}/{accion}.json`.

Documentación: [`docs/arquitectura/asistente-motores.md`](../../../../docs/arquitectura/asistente-motores.md), [`docs/producto/asistente-y-chat.md`](../../../../docs/producto/asistente-y-chat.md).
