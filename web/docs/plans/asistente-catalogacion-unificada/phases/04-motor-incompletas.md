# Fase 04 — Motor `funcionalidades_incompletas`

## Objetivo

2ª IA solo cuando hace falta sintetizar con datos del HIS.

## Tareas

- [ ] Prompt `prompts/synthesis.yaml` (o renombrar `guide.yaml`): necesidad + scoped records; sin catálogo completo.
- [ ] `IncompleteCatalogacionHandler`: hidratar vía `AssistantContextAssemblyService` (anclas + aspectos desde `context_areas`, `extractions`, `intent_ids`).
- [ ] Input 2ª IA: solo `necesidad_usuario` de la 1ª IA (no historial chat).
- [ ] Output: texto + opcional botón si intent de atención/turno (desde `intent_ids`, no regex).
- [ ] Casos: llegar tarde (políticas centro), comparar profesionales (listado + límites), preguntas con `scoped_system_records` vacío → respuesta honesta.

## Criterio de salida

Tests con fixtures de aspect loaders; 2ª IA mockeada; reemplaza flujo guide para esos casos en tests de routing.
