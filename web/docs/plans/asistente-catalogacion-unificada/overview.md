# Overview — Catalogación unificada del asistente

## Problema

Hoy el asistente mezcla varias «cerebros»:

- Preprocess IA (`user_goal`, `context_areas`, extracciones).
- Canal `guide` (2ª IA conversacional + volcado HIS).
- `IntentClassifier` por keywords (sin IA).
- `ChatChannelPolicy` por regex (CTA síntoma, políticas citas).

Eso genera duplicación (mismo mensaje interpretado dos veces), canal guide que no es el producto («no somos asistente de salud») y fricción entre charla y trámites.

## Objetivo

Un **primer paso IA** que clasifica cada mensaje del chat como **uso del HIS** usando el **catálogo completo** de funcionalidades permitidas al usuario (`UiActionCatalog::forUser`). Cuatro resultados:

| Catalogación | Acción |
|--------------|--------|
| **clara** | Texto de la 1ª IA + ejecución o botones según cantidad de `intent_ids` |
| **dudosa** | Preguntas al usuario (JSON directo al cliente) |
| **funcionalidades_incompletas** | 2ª IA con `necesidad_usuario` + datos hidratados en PHP |
| **fuera_de_his** | Respuesta acotada (tema no consultable al sistema) |

No hay charla de salud libre: síntomas y malestar se asocian a intents del catálogo (p. ej. `atencion.necesito-atencion`) vía **summary** bien escrito, no vía regex PHP previa al preprocess.

## Entregables

1. Contrato JSON de la 1ª IA documentado (ADR).
2. `{funcionalidades}`: JSON compacto con **todas** las UIs del usuario (sin top-K).
3. Summaries de clasificación recortados en intents (`intent_semantics` o campo dedicado).
4. Router que interpreta `catalogacion` y delega a `IntentEngine` / síntesis / ambiguous.
5. Motor **incompletas** (evolución de guide): 2ª IA solo con necesidad explícita + `scoped_system_records`.
6. Regla **clara**: 1 `intent_id` → `kind: flow`; varios → `kind: interactive`; 0 → fail suave (solo texto).
7. Tests unitarios + QA conversacional.
8. Documentación estable y borrado de este plan.

## Fuera de alcance (este programa)

- Cambiar contratos de `SubIntentEngine` o YAML de flows existentes (solo summaries y cableado).
- Reemplazar keywords de `IntentClassifier` en operational si la 1ª IA ya eligió intent (salvo fallback).
- Nuevos intents de negocio masivos (solo los mínimos para políticas de cita / QA sin intent hoy).
- Top-K o embedding retrieval del catálogo.

## Actores

- Producto / metadata: summaries y ejemplos en intents.
- Backend: preprocess, router, incompletas, envelope.
- Clientes (web, móvil, WhatsApp): consumir envelope unificado (`interactive` sin `message` separado).

## Criterio de cierre

- [ ] No queda canal `guide` en el camino raíz del chat.
- [ ] Preprocess no usa `user_goal` guide/operational como eje principal (usa `catalogacion`).
- [ ] Casos QA acordados pasan (síntoma, turno, política llegada tarde, listado staff, fuera HIS).
- [ ] ADR + `producto/asistente-y-chat.md` actualizados; carpeta `plans/asistente-catalogacion-unificada/` eliminada.
