# Fase 5 — Área = carpeta del intent

## Objetivo

Que el área HIS se lea del árbol en vez de declararse (y en vez de pedírsela a la 1ª IA). Es el pago de la Fase 3: los intents ya viven bajo su dominio.

## Estado

**Implementado en código** (pendiente: smoke QA usuario + aplicar sugerencia de `preprocess/prompt.yaml` a mano).

## Qué quedó hecho

### 5.1 Una sola palabra

- [x] Área = id de carpeta de dominio (`scheduling`, `clinical`, `person`, …) o solo-contexto
- [x] Quitado `his_areas` de los 4 intents que lo declaraban; `IntentEngine` lo deriva de `IntentSchemaPaths::domainForIntentId()`
- [x] `AssistantContextHISArea`: constantes que el código ramifica (`SCHEDULING`, `CLINICAL`, `PRODUCT`, …); catálogo YAML con 7 ids

### 5.2 Derivar el área

- [x] `AssistantContextAreaDerivation`: match → `tool_ref` / `cta_intent_ids` → carpeta; triggers de área en entradas aspect/article
- [x] Inyección post-match en `SmartCatalogRoutingService` + sync a `ChatPreprocessContext` en `ChatRouter`
- [x] Solo-contexto: `product`, `geo_resources` (`context_only: true` en YAML)

### 5.3 Dejar de pedir áreas a la 1ª IA

- [x] `first-ia-v1.yaml`: `context_areas` fuera de required (deprecated/ignored)
- [x] `normalizeFromAi` siempre `context_areas: []`
- [x] Borrados `reconcileContextAreasForSymptom` / `reconcileTagsForSymptom`
- [x] Guide: líneas de ámbito desde áreas derivadas; HC se adjunta siempre (cambio previo)

### 5.4 Prompt (solo sugerencia)

- [ ] Diff de `preprocess/prompt.yaml` entregado en el chat (usuario aplica a mano)

## Criterios de aceptación

- [x] Catálogo sin ids huérfanos: dominio o `context_only`
- [ ] Smoke `smoke-sintoma-cabeza`: sin área inventada; **sin** reconcile
- [ ] Smoke `smoke-quiero-turno`: canal y CTA sin cambios
- [x] Guide recibe líneas de ámbito desde áreas derivadas

## PR sugerido

`refactor(assistant): área HIS derivada de la carpeta del intent`
