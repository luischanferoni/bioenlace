# Overview — Contexto HIS para el asistente

## Problema

El asistente paciente usa dos llamadas IA frecuentes (`asistente-preprocess`, `asistente-conversational` / informational). Hoy:

- El **preprocess** solo conoce `user_goal` y categorías de extracción (`turno`, `efector`, …); no tiene mapa del **HIS** (citas, reglas del centro, historial, etc.).
- La **2ª IA** recibe poco contexto operativo (extracto HC opcional en `clinical`; artículo solo en `informational` con match).
- No hay mecanismo escalable para **volcar datos del dominio** sin enumerar preguntas en PHP ni duplicar manifests YAML.

Conversación de diseño: citas tardías, políticas del centro, qué historial aporta valor — ver hilo de producto 2026-08.

## Objetivo

Introducir un motor de **contexto HIS** con dos niveles:

| Nivel | Clase / concepto | Quién lo usa |
|-------|------------------|--------------|
| Área HIS | `AssistantContextHISArea` | Preprocess (prompt + output `context_areas`) |
| Aspecto | `AssistantContextHISAreaAspect` | Loaders PHP + volcado 2ª IA |

Flujo:

1. Preprocess recibe catálogo **corto de áreas** (no aspectos finos).
2. Preprocess devuelve `context_areas: []` en saludos; `["appointments"]` cuando el mensaje requiere datos de citas.
3. PHP (`AreaAspectResolver`) traduce áreas + extracciones + canal → lista de **aspectos**.
4. PHP (`AspectLoaderRegistry`) ejecuta un método por aspecto → JSON HIS.
5. `AssistantContextAssemblyService` adjunta bloques al prompt de la 2ª IA (cuando el canal usa IA).

## Resultado esperado (MVP)

- Catálogo v1 de **áreas HIS** en prompt preprocess (~10–15 ítems).
- `context_areas` en JSON de preprocess validado contra enum PHP.
- Registry de **aspectos** con loaders en dominio; mapeo área→aspectos en PHP (sin YAML de bloques).
- Volcado JSON con `scope_applied` para debug/QA.
- Integración mínima: canal **informational** con IA (además de artículo) y **clinical** (sustituir/ampliar `ConversationalChannelProviderRegistry` gradualmente).
- Caso referencia documentado: pregunta sobre llegada tarde a cita (aspectos de citas + reglas del centro, no historial masivo por defecto).

## Fuera de alcance inicial

- RAG / búsqueda semántica sobre `info_content` (fase posterior).
- Volcado de HC completa o catálogo de intents / `intent_semantics`.
- Agregados de **otros pacientes** del mismo centro en chat paciente (solo datos del sujeto autorizado).
- Staff: áreas adicionales (tablero guardia, KPIs) — extensión del mismo motor, no MVP paciente.
- Segunda IA en canal `operational` (sigue reglas + flows).
- Reemplazar Data Access / intents de lectura para datos factuales exactos (“¿a qué hora es mi turno?” puede seguir siendo operacional).

## Fases

Ver [phases/](./phases/). Orden: **0 → 1 → 2 → 3 → 4**.

## Criterios de cierre

- [ ] `AssistantContextHISArea` y `AssistantContextHISAreaAspect` implementados con catálogo v1 documentado en design.
- [ ] Preprocess ampliado sin romper goals/canales existentes.
- [ ] Al menos 3 aspectos de área `appointments` con loaders reales.
- [ ] QA: saludo → `context_areas: []` sin loaders; tardanza → volcado acotado + sin inventar tolerancia.
- [ ] Docs estables actualizadas; carpeta `plans/asistente-contexto-his/` eliminada.
