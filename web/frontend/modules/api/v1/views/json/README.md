# UI JSON templates (API v1)

Descriptores servidos por `UiScreenService` / `UiDefinitionTemplateManager`.

## Carpetas por dominio

| Carpeta | Entidades |
|---------|-----------|
| `scheduling/` | `turnos`, `profesional-agenda`, `efectores`, `servicios` |
| `clinical/` | `care-plan`, `care-plans`, `encounter`, `internacion`, … |
| `persona/` | `persona` |
| `organization/` | `profesional-efector-servicio` |

Las rutas HTTP públicas **no cambian** (ej. `GET /api/v1/turnos/crear-como-paciente`). Clínica UI: `GET /api/v1/clinical/care-plan/ver-tratamiento-paciente`.

Cada descriptor vive bajo su entidad y acción exactas. Si hay rutas públicas alternativas, cada una tiene su propio descriptor para que la resolución no dependa de alias en Platform.

Convención en código: `handleScreen('<entidad>', '<accion>', …)` — el manager resuelve `{dominio}/{entidad}/{accion}.json`.

Documentación: [`docs/arquitectura/asistente-motores.md`](../../../../docs/arquitectura/asistente-motores.md), [`docs/producto/asistente-y-chat.md`](../../../../docs/producto/asistente-y-chat.md).
