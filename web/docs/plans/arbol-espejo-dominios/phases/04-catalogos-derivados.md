# Fase 4 — Catálogos con ids del dominio y texto en YAML

## Objetivo

Que `preprocess-extraction-categories.yaml` deje de ser una lista cerrada mantenida a mano: los **ids** los aportan los dominios, el **texto** queda en YAML, y un test cierra el círculo. Sin archivos generados.

## Estado hoy

- 10 categorías en el YAML; **3** se usan de verdad (`servicio`, `efector`, `profesional` vía `hint.entity` en 3 intents)
- `persona` está en `ENTITY_OWNERSHIP` pero ningún intent la declara
- `sintoma`, `medicamento`, `acto`, `turno` **duplican** el eje de tags (ya derivado del smart-catalog)
- Precedente correcto en el repo: `PreprocessTagVocabularyCatalog` compone en runtime, sin YAML paralelo

## Tareas

### 4.1 Declaración por dominio

- [ ] Cada dominio declara qué entidades resuelve, junto a su `HintCandidateProvider` (`scheduling` → `servicio`; `organization` → `servicio`, `efector`, `profesional`; `person` → `persona`)
- [ ] `HintResolutionMetadata::ENTITY_OWNERSHIP` pasa a componerse desde esas declaraciones en lugar de ser un mapa central

### 4.2 Reducir el catálogo a entidades resolubles

- [ ] Quitar del vocabulario de extracción lo que ya es tag (`sintoma`, `medicamento`, `acto`, `turno`, `tiempo`, `obra_social`, `ubicacion`), verificando antes que ningún consumidor dependa de esa `category`
- [ ] Revisar `AssistantContextAreaAspectCatalog::wantsAppointmentHistory()` (usa el **span**, no la category) y `DataAccessEditDiscoveryService`
- [ ] El YAML queda solo con `id → texto` de entidades resolubles

### 4.3 Cerrar el círculo con tests

- [ ] Id declarado por un dominio sin texto en YAML → falla
- [ ] Texto en YAML sin dominio que lo declare → falla
- [ ] Todo `hint.entity` de los intents pertenece al catálogo
- [ ] Ampliar `AssistantCatalogSourceOfTruthTest`

### 4.4 Prompt

- [ ] El placeholder `{extraction_categories_list}` sigue saliendo del loader; **no** se toca el cuerpo del prompt
- [ ] Si hace falta ajustar redacción: dejar la sugerencia en el chat para que la aplique el usuario

## Fuera de esta fase

- Áreas (Fase 5).
- Generar YAML en deploy: descartado, ver `design.md` §7.

## Criterios de aceptación

- [ ] Agregar un provider de hint hace aparecer la entidad en el prompt y **obliga** a agregar su texto
- [ ] Borrar un provider deja el texto marcado como huérfano por el test
- [ ] Ninguna palabra vive a la vez como entidad de extracción y como tag
- [ ] QA del asistente sin regresión en resolución de hints (reserva de turno, triage)

## PR sugerido

`refactor(assistant): entidades de extracción declaradas por dominio, texto en YAML`
