# Fase 5 — Área = carpeta del intent

## Objetivo

Que el área HIS se lea del árbol en vez de declararse (y en vez de pedírsela a la 1ª IA). Es el pago de la Fase 3: los intents ya viven bajo su dominio.

## Estado hoy

- `his_areas` está declarado en **4 de ~55** intents; la verdad efectiva la pone la IA en cada turno vía `context_areas`
- Un concepto con dos nombres: `context_areas` (preprocess, 14 usos en `smart-catalog.yaml`) y `his_areas` (intents)
- `context-his-areas.yaml` tiene 9 ids duplicados como constantes en `AssistantContextHISArea`
- PHP ya corrige a la IA: `reconcileContextAreasForSymptom()` saca `clinical_record` cuando el mensaje es síntoma

## Tareas

### 5.1 Una sola palabra

- [ ] Elegir el término único para el concepto y usarlo en intents, smart-catalog y preprocess
- [ ] Quitar el campo declarativo cuando el área se derive de la carpeta del intent
- [ ] Dejar en `AssistantContextHISArea` solo las constantes que el código realmente ramifica (p. ej. `clinical_record`), no las 9

### 5.2 Derivar el área

- [ ] Área de un intent = su carpeta de dominio
- [ ] Áreas activas del turno = derivadas del match del smart-catalog → entry → `tool_ref` / `cta_intent_ids` → intent → carpeta
- [ ] Áreas que no tienen intents (`product`, `geo_resources`) se marcan explícitamente como solo-contexto en el catálogo

### 5.3 Dejar de pedir áreas a la 1ª IA

- [ ] Quitar `context_areas` del contrato de salida (`first-ia-v1.yaml`) y del normalize
- [ ] Borrar `reconcileContextAreasForSymptom()` / `reconcileTagsForSymptom()`: sin áreas inventadas, no hay qué corregir
- [ ] Guide toma las áreas derivadas para `{context_his_areas_lines}` y para el adjunto de HC

### 5.4 Prompt (solo sugerencia)

- [ ] Preparar el diff propuesto de `preprocess/prompt.yaml` (quitar el bloque `context_areas`) y **entregarlo en el chat**
- [ ] No editar el archivo: lo aplica el usuario

## Fuera de esta fase

- Estado de conversación / tema resuelto (`open`/`resolved`) para acotar el historial: tema aparte.

## Criterios de aceptación

- [ ] El catálogo de áreas no tiene ids huérfanos: cada uno tiene intents o está marcado solo-contexto
- [ ] Smoke `smoke-sintoma-cabeza`: `clinical_record` no aparece, **sin** código de reconcile
- [ ] Smoke `smoke-quiero-turno`: canal y CTA sin cambios
- [ ] Guide sigue recibiendo las líneas de ámbito correctas

## PR sugerido

`refactor(assistant): área HIS derivada de la carpeta del intent`
