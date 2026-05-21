# Fase 9 — Asistente (ClinicalEncounter, intents, drafts)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md)  
**Estado:** hecho

## Objetivo

Alinear el asistente con Encounter/CarePlan: drafts, entry points, catálogo de acciones y YAML de intents.

## Tareas

- [x] `Assistant/EntryPoints/ClinicalEncounter/` delega a `EncounterDocumentationService` (`analizar()` aún usa `Legacy/ConsultaProcesamientoService` — pipeline IA legacy, sin tablas `consultas_*`).
- [x] `AppointmentReasonEntry`: API y persistencia por `encounter_id` (`consulta_id` alias en request/response).
- [x] Draft: `AssistantDraftNormalizer` — `encounter_id` / `care_plan_id`; sin `intent_id` en draft; alias `id_consulta` → `encounter_id` (SubIntentEngine + IntentEngine).
- [x] `ClinicalUiActionCatalog` + merge en `UiActionCatalog` (rutas `/api/clinical/...` con RBAC).
- [x] YAML intents: `encounter_id` / `care_plan_id` en [YAML_INTENTS_CONTRACT.md](../../asistente/flows/YAML_INTENTS_CONTRACT.md).
- [x] Canales: síntomas/malestar no abren menú de capacidades (`InformationalChannel` + `ChatPreprocessService::isClinicalSymptomContent` + prompt conversacional).

## Fuera de alcance (otras fases)

| Tema | Fase |
|------|------|
| Intent YAML «ver mi tratamiento» + UI JSON | [11](./11-ui-json-clinical.md) |
| Migrar `analizar()` IA fuera de `ConsultaProcesamientoService` | post-programa / mejora IA |
| Descubrimiento recursivo de todos los controllers `clinical/*` en ActionDiscoveryService | opcional; catálogo estático cubre fase 9 |

## Definition of Done

- [x] API clinical operativa; legacy `consulta/*` → 410.
- [x] Captura clínica vía `ClinicalEncounterEntry` → `encounter/guardar`.
- [x] Draft y motivos pre-consulta usan `encounter_id` como identificador canónico.

## Siguiente fase del programa

[Fase 10 — Móvil paciente](./10-mobile-paciente.md) (ya entregada; continuar con [11](./11-ui-json-clinical.md) si el canal es UI JSON).
