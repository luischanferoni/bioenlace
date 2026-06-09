# Fase 5 — Móvil, asistente y notificaciones

**Estado:** pendiente

## Objetivo

UX paciente/tutor: elegir sujeto, gestionar vínculos y delegaciones; asistente con intents YAML.

## App paciente (Flutter)

| Pantalla / flujo | Descripción |
|------------------|-------------|
| Selector “A cargo de” | Header o chip: Yo \| Hijo Juan \| … |
| Mis hijos (A) | Lista vínculos tutor; CTA solicitar alta menor |
| Mis representantes (B) | Paciente designa / revoca |
| Pacientes a mi cargo (B) | Representante ve lista y entra en contexto |
| Preferencias | Toggle notificación acciones representante (N9) |

Al cambiar sujeto: propagar `subject_persona_id` a home turnos, motivos, pre-consulta.

## Asistente

| Artefacto | Contenido |
|-----------|-----------|
| `personas.vincular-menor-flow.yaml` | Tutor → datos hijo → pending staff (o mensaje espera verificación) |
| `personas.designar-representante-flow.yaml` | Paciente → buscar persona → confirmar |
| Catálogo acciones | `PersonRepresentationUiActionCatalog` si aplica |

## Staff móvil / web

- Verificación vínculo pendiente (Fase 2) en flujo admisión o módulo paciente.

## Checklist

- [ ] `shared` helper contexto sujeto (como care_pack_navigation)
- [ ] Push opcional al paciente si `notify_on_representative_action`
- [ ] Intents + RBAC
- [ ] E2E: padre saca turno hijo desde móvil

## Fuera de fase

- FHIR REST público RelatedPerson/Consent
- Unificar diabetes `PARENTESCO` con catálogo global
