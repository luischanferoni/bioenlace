# Fase 01 — Summaries compactos + `{funcionalidades}`

## Objetivo

Catálogo completo en preprocess sin explosion de tokens.

## Tareas

- [ ] Definir payload clasificador: extender `toAiCandidateArray()` o nuevo `toClassifierArray()` (solo `id`, `summary`, `ejemplos`).
- [ ] Servicio `AssistantClassifierCatalogBuilder::forUser($userId)` → JSON string para placeholder `{funcionalidades}`.
- [ ] Auditar intents: recortar `intent_semantics.summary`; mover `capabilities` fuera del payload clasificador (mantener en YAML para flows/guide legacy hasta fase 05).
- [ ] Prioridad intents críticos: `atencion.necesito-atencion`, turnos crear/cancelar/ver, `data-access.info|listar`, profesionales listado, política autogestión.
- [ ] Test: tamaño de `{funcionalidades}` paciente vs staff (registrar tokens estimados en nota de fase).

## Criterio de salida

Builder genera JSON válido; al menos intents prioritarios con summaries nuevos; test unitario del builder.
