# Fase 5 — Móvil, asistente y notificaciones

**Estado:** implementada

## Objetivo

UX paciente/tutor: elegir sujeto, gestionar vínculos y delegaciones; asistente con intents YAML.

## App paciente (Flutter)

| Pantalla / flujo | Descripción |
|------------------|-------------|
| Selector “A cargo de” | Chip en inicio (`PersonRepresentationSubjectChip`) |
| Mis hijos (A) | Hub: lista vínculos tutor + solicitar alta menor |
| Mis representantes (B) | Hub: designar / revocar |
| Pacientes a mi cargo (B) | Hub: lista + «Operar» fija contexto |
| Preferencias | Toggle `notify_on_representative_action` (N9) |

Al cambiar sujeto: `subject_persona_id` en turnos, care plans, pre-consulta y sesión API.

## Asistente

| Artefacto | Contenido |
|-----------|-----------|
| `personas.vincular-menor-flow.yaml` | Abre hub nativo / submit solicitud tutela |
| `personas.designar-representante-flow.yaml` | Abre hub nativo / submit designación |
| `PersonRepresentationUiActionCatalog` | Registrado en `UiActionCatalog` |

## Notificaciones N9

- `PersonRepresentationDelegatedActionNotifier` — push + inbox si el paciente activó la preferencia.
- Hook en `PersonRepresentationSubjectService::auditDelegatedAction`.
- Tipo FCM: `REPRESENTATIVE_ACTION`.

## Checklist

- [x] `shared` helper contexto sujeto (`person_representation_context.dart`)
- [x] Push opcional al paciente si `notify_on_representative_action`
- [x] Intents YAML + catálogo acciones
- [ ] E2E manual: padre saca turno hijo desde móvil (validar en dispositivo)

## Documentación estable

- [producto/representacion-paciente.md](../../../producto/representacion-paciente.md)
- Técnica: `common/components/Domain/Person/Representation/README.md`

## Fuera de fase

- FHIR REST público RelatedPerson/Consent
- Unificar diabetes `PARENTESCO` con catálogo global
