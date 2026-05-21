# Fase 9 — Asistente (ClinicalEncounter, intents, drafts)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md)  
**Estado:** en_curso (2026-05-20)

## Objetivo

Alinear el asistente con Encounter/CarePlan: drafts, entry points, catálogo de acciones y YAML de intents.

## Tareas

- [x] `Assistant/EntryPoints/ClinicalEncounter/` delega a `Clinical/Workflow/EncounterDocumentationService` (`analizar()` aún usa `Legacy/ConsultaProcesamientoService`).
- [ ] `AppointmentReasonEntry` revisar naming; sigue en motivos pre-consulta si aplica.
- [ ] Draft del asistente: `encounter_id`, `care_plan_id` en lugar de `intent_id` + draft solo turnos donde corresponda.
- [ ] Catálogo `UiActionCatalog`: rutas `/api/v1/clinical/...` para acciones clínicas futuras.
- [x] YAML intents: `encounter_id` / `care_plan_id` documentados en [../../asistente/YAML_INTENTS_CONTRACT.md](../../asistente/YAML_INTENTS_CONTRACT.md).
- [ ] Canales preprocess/conversational: si el usuario describe síntomas, no ofrecer menú de turnos (ya corregido en Informational; validar con dominio nuevo).

## Fuera de alcance

- Nuevos intents de “ver mi tratamiento” (API fase 10 lista; falta UI JSON / intent YAML).

## Definition of Done

- [x] API clinical operativa; legacy `consulta/*` → 410. Clientes móvil/web deben usar solo rutas nuevas.
- Tests manuales: mensaje de voz/texto en captura clínica persiste en Encounter.

## Siguiente fase

[Fase 10 — Móvil paciente](./10-mobile-paciente.md)
