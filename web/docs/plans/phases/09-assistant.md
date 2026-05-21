# Fase 9 — Asistente (ClinicalEncounter, intents, drafts)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md)  
**Estado:** pendiente

## Objetivo

Alinear el asistente con Encounter/CarePlan: drafts, entry points, catálogo de acciones y YAML de intents.

## Tareas

- [ ] `Assistant/EntryPoints/ClinicalEncounter/` delega a `Clinical/Workflow/EncounterDocumentationService` (no `ConsultaProcesamientoService`).
- [ ] `AppointmentReasonEntry` revisar naming; sigue en motivos pre-consulta si aplica.
- [ ] Draft del asistente: `encounter_id`, `care_plan_id` en lugar de `intent_id` + draft solo turnos donde corresponda.
- [ ] Catálogo `UiActionCatalog`: rutas `/api/v1/clinical/...` para acciones clínicas futuras.
- [ ] YAML intents: documentar `encounter_id` / parámetros CarePlan en [../../asistente/YAML_INTENTS_CONTRACT.md](../../asistente/YAML_INTENTS_CONTRACT.md).
- [ ] Canales preprocess/conversational: si el usuario describe síntomas, no ofrecer menú de turnos (ya corregido en Informational; validar con dominio nuevo).

## Fuera de alcance

- Nuevos intents de “ver mi tratamiento” hasta fase 10 exponga API (puede prepararse catálogo).

## Definition of Done

- Flujo asistente que hoy llama `consulta/analizar|guardar` usa rutas clinical.
- Tests manuales: mensaje de voz/texto en captura clínica persiste en Encounter.

## Siguiente fase

[Fase 10 — Móvil paciente](./10-mobile-paciente.md)
