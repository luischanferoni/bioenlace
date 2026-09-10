# Fase 4 — Catálogos con ids del dominio y texto en YAML

## Objetivo

Que `preprocess-extraction-categories.yaml` deje de ser una lista cerrada mantenida a mano: los **ids** los aportan los dominios, el **texto** queda en YAML, y un test cierra el círculo. Sin archivos generados.

## Estado al cerrar

- YAML reducido a 4 entidades resolubles: `servicio`, `efector`, `profesional`, `persona`
- Quitados del vocabulario de extracción (viven como **tags** o no tenían consumidor): `sintoma`, `medicamento`, `acto`, `turno`, `tiempo`, `obra_social`, `ubicacion`
- `ENTITY_OWNERSHIP` central eliminado: ownership compuesto desde `declaredEntities()` de cada provider
- Placeholder `{extraction_categories_list}` sigue saliendo del loader; **no** se tocó el cuerpo del prompt

## Tareas

### 4.1 Declaración por dominio

- [x] `HintCandidateProviderInterface::declaredEntities()`
  - `scheduling` → `servicio`
  - `organization` → `servicio`, `efector`, `profesional`
  - `person` → `persona`
- [x] `HintCandidateProviderRegistry::entityOwnership()` / `allDeclaredEntities()`
- [x] `HintResolutionMetadata::providerKeysForEntity()` delega al registry (sin mapa a mano)

### 4.2 Reducir el catálogo a entidades resolubles

- [x] YAML solo con id → texto de entidades resolubles
- [x] `PreprocessExtractionCategoryCatalog::all()` = ids declarados por providers; textos desde YAML
- [x] Verificado: `wantsAppointmentHistory` usa span; `DataAccessEditDiscoveryService` no usa category
- [x] Fixtures de tests actualizados; `sintoma`/`tiempo`/`turno` como category se descartan en normalize

### 4.3 Cerrar el círculo con tests

- [x] Id declarado sin texto en YAML → falla (`testDeclaredEntitiesHaveYamlText`)
- [x] Texto en YAML sin dominio que lo declare → falla (`testYamlExtractionTextsHaveDeclaringDomain`)
- [x] Todo `hint.entity` de intents ⊆ catálogo (`testIntentHintEntitiesBelongToCatalog`)
- [x] Entidad no puede ser también tag (`testExtractionCategoriesDoNotOverlapPreprocessTags`)
- [x] `AssistantCatalogSourceOfTruthTest` y `PreprocessExtractionCategoryCatalogTest` ampliados

### 4.4 Prompt

- [x] `{extraction_categories_list}` sigue del loader (lista más corta sola)
- [x] Sin cambio de cuerpo de prompt (no hace falta sugerencia)

## Fuera de esta fase

- Áreas (Fase 5).
- Generar YAML en deploy: descartado.

## Criterios de aceptación

- [x] Agregar un provider con `declaredEntities()` exige texto en YAML (test)
- [x] Texto huérfano en YAML falla el test
- [x] Ninguna palabra vive a la vez como entidad de extracción y como tag
- [ ] QA del asistente sin regresión en resolución de hints (reserva de turno, triage) — smoke del usuario

## PR sugerido

`refactor(assistant): entidades de extracción declaradas por dominio, texto en YAML`
