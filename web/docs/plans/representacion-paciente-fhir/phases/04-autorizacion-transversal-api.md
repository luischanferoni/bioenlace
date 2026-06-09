# Fase 4 — Autorización transversal en API

**Estado:** pendiente

## Objetivo

Un actor con representación válida puede usar los flujos paciente existentes sobre el sujeto, con `subject_persona_id` explícito o contexto operativo.

## Endpoints a adaptar (permisos v1)

| Dominio | Controller / servicio | Permiso |
|---------|----------------------|---------|
| Turnos | `TurnosController` crear/listar/cancelar como paciente | `scheduling.turno` |
| Motivos | motivos-consulta API | `clinical.motivos` |
| Cohortes | `CarePacksController` assistance | `clinical.care_pack_assistance` |
| Tratamiento | care-plan / recetas paciente | `clinical.care_plan` |
| HC resumen | endpoints paciente lectura HC | `clinical.historia_resumen` |

## Patrón

1. Resolver `subject_persona_id` (body, query o sesión operativa paciente).
2. Si `subject === getIdPersona()` → flujo actual.
3. Si distinto → `PersonRepresentationAccessService::canAct(actor, subject, permission)`.
4. Persistir turno/encounter con `id_persona = subject`.
5. `person_related_audit_log`.

## Contexto operativo paciente (opcional)

API `POST /api/v1/sesion-operativa/establecer-sujeto-paciente` o reutilizar patrón análogo a staff:

- `subject_persona_id` en sesión para móvil “estoy operando por Juan”.
- Documentar en reglas API: paciente móvil puede no tener efector pero sí sujeto delegado.

## Checklist

- [ ] Refactor `EncounterAccessService` — delegación acotada por permiso
- [ ] `CarePackAssistanceService` — actor delegado
- [ ] Tests integración API turno por hijo (A) y por paciente delegado (B)
- [ ] Sin hardcode de régimen en controllers

## Criterios de aceptación

- Padre verificado crea turno para hijo menor.
- Representante designado carga motivos para paciente.
- Tras revocación B, siguiente request 403.
