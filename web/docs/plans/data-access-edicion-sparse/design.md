# Design — Edición dispersa

## RBAC (capas)

1. **Ruta** `/api/editar` — ApiGhost (hereda de `/api/listar` en migración).
2. **Superficies** — `userHasAnyEditableSurface`: ∃ superficie con ≥1 aspecto `write` + scope OK.
3. **Superficie concreta** — `userCanAccessEditSurface(surface_id)`.
4. **Aspecto** — `can(ctx, attribute_group, WRITE)`; picker solo muestra permitidos.
5. **Ejecución** — revalidar cada campo al confirmar.

## Metadata (`attribute_groups_v1.yaml`)

```yaml
edit_surfaces:
  profesional_en_efector:
    label: Personal del centro
    scope_checker: efector_sesion
    subject_resolver:
      metric_id: profesionales_listado_efector
      selection_param: id_persona
    aspects:
      identidad:
        kind: scalar_group
        attribute_group: Persona.identidad_basica
      agenda_horarios:
        kind: open_ui
        attribute_group: ProfesionalEfectorServicio.asignacion
        ui_action: profesional-agenda.configurar-agenda
```

`role_grants` gana operación `write` en grupos editables.

## API `/api/v1/editar`

| Parámetros | Respuesta |
|------------|-----------|
| (vacío) | ui_json picker de superficies autorizadas |
| `surface_id` | ui_json picker de aspectos |
| `surface_id` + `step=subjects` | delega a listar (`subject_resolver.metric_id`) |
| Fase 2+ | `step=form`, `step=confirm`, `POST` mutar |

## Asistente

- Intent único `data-access.editar` (no uno por superficie).
- Hydrator `data_access.edit_flow` resuelve `surface_id` desde keywords.
- Keywords solo si `userHasAnyEditableSurface`.
