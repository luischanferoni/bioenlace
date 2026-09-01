# Design — Catalogación unificada

## Principio

El chat es una **interfaz de comandos** hacia un software de Información Hospitalaria. La 1ª IA recibe el mensaje (y contexto de sesión/historial) más el catálogo de funcionalidades del usuario; devuelve **solo JSON**. PHP ejecuta, hidrata datos del HIS y arma el envelope al cliente.

No somos asistente clínico: no diagnosticamos en texto libre. Síntomas y malestar → intent del catálogo (triage / solicitar atención), no canal conversacional de salud.

## Flujo

```
Usuario → ChatOrchestrator
    → 1ª IA (preprocess unificado)
        catalogacion + intent_ids + respuesta + necesidad_usuario + extracciones
    → PHP según catalogacion
        clara + 1 intent   → IntentEngine / SubIntentEngine → kind: flow
        clara + N intents  → kind: interactive (text + buttons)
        clara + 0 intents  → fail suave (solo text; telemetría)
        dudosa             → interactive o message con preguntas
        fuera_de_his       → text al cliente
        incompletas        → loaders HIS + 2ª IA → text (+ CTA si aplica)
```

## Catálogo `{funcionalidades}`

- Fuente: `UiActionCatalog::forUser($userId)` — **todas** las acciones permitidas (RBAC ya aplicado).
- Formato: JSON array compacto; base existente `UiActionCatalogItem::toAiCandidateArray()` (`id`, keywords, semantics).
- **Sin top-K**: la clasificación debe ver el menú completo del usuario.
- Costo controlado por **summaries cortos** (ver fase 01), no por recortar candidatos.
- Cache de contexto IA: prompt estable + catálogo por rol/sesión cuando el proveedor lo permita.

### Contenido mínimo por funcionalidad (clasificador)

| Campo | Uso |
|-------|-----|
| `id` | `intent_id` |
| `summary` | 1–2 líneas: qué necesidad del usuario matchea |
| `ejemplos` | 2–4 frases típicas (opcional, mejor que 30 keywords) |

**No** incluir en preprocess: `capabilities` largas, pasos de flow, copy de UI, `his_areas` (PHP los usa después).

## JSON 1ª IA (borrador)

```json
{
  "catalogacion": "clara | dudosa | funcionalidades_incompletas | fuera_de_his",
  "normalized_text": "mensaje del usuario limpio",
  "necesidad_usuario": "una frase: qué necesita (obligatorio en incompletas; recomendado siempre)",
  "respuesta_al_usuario": "texto para mostrar o vacío si solo ejecuta flow",
  "intent_ids": ["..."],
  "extractions": [
    { "span": "...", "category": "...", "synonyms": [] }
  ],
  "context_areas": ["appointments"]
}
```

Reglas de producto:

- **clara**: `intent_ids` no vacío (si vacío → error de clasificación, cliente muestra solo `respuesta_al_usuario`).
- **dudosa**: preguntas en `respuesta_al_usuario` (o campo `preguntas` si se separa).
- **funcionalidades_incompletas**: `necesidad_usuario` obligatorio; `intent_ids` opcional (orienta loaders); **no** historial en 2ª IA.
- **fuera_de_his**: temas no consultables al HIS (clima, chistes); **no** opiniones subjetivas sobre profesionales si el usuario pide comparar con datos del centro → incompletas.

## Hidratación (patrón `scoped_system_records`)

La 1ª IA **no** elige aspectos SQL ni loaders. PHP:

1. Toma `intent_ids`, `context_areas`, `extractions`.
2. Resuelve anclas (`AssistantContextAnchorResolver`).
3. Plan de aspectos (`AssistantContextAreaAspectResolver`) o métricas DataAccess.
4. Ejecuta loaders → `scoped_system_records` (mismo bloque `context:his` que guide hoy).

Si tras hidratar no hay datos suficientes, se puede degradar a `dudosa` o respuesta honesta en 2ª IA.

**No** agregar `answer_mode` en cada intent YAML: la IA marca `funcionalidades_incompletas`; PHP carga lo que el dominio ya sabe resolver.

## Catalogación `clara` → envelope

| `intent_ids` | PHP | `kind` |
|--------------|-----|--------|
| 1 | `IntentEngine::buildSingleActionResponse` → flow si aplica | `flow` |
| >1 | No ejecutar; botones = intents validados en catálogo | `interactive` |
| 0 | Log warning; no botones | texto solo (degradación de `interactive`) |

`kind: message` **no** es necesario en el contrato nuevo: siempre hay texto; botones opcionales (`AssistantEnvelope::interactive` ya degrada a solo texto si `buttons` vacío).

Info/list (`data-access.info`, `data-access.listar`): con un solo intent, PHP ejecuta flow catalog-only y el dato va en UI/API del paso, no en el párrafo de la 1ª IA.

## 2ª IA (`funcionalidades_incompletas`)

Reemplazo del canal guide para síntesis:

- Input: `necesidad_usuario` (una frase, de la 1ª IA) + `scoped_system_records` + opcional semantics del intent principal.
- **Sin** `{funcionalidades}` completo otra vez.
- **Sin** historial de chat crudo (la necesidad ya está explicitada).
- Prompt en metadata YAML (`prompts/synthesis.yaml` o evolución de `guide.yaml`).

Casos: llegar tarde al turno, comparar profesionales con datos del centro, preguntas que requieren políticas del efector.

## Sesión e `in_flow_question`

Preprocess adjunta:

- Historial de mensajes reciente (curado por ventana, no dump infinito).
- Estado de flow si existe: `intent_id`, `subintent_id`, draft (desde sesión).

Summaries deben mencionar follow-ups dentro del trámite abierto.

## Deprecaciones

| Hoy | Destino |
|-----|---------|
| `user_goal: guide` | `catalogacion` + incompletas o clara con intents read |
| Canal `GuideChannel` | Motor incompletas + clara con flow |
| `ChatChannelPolicy::isClinicalSymptomContent` para CTA | Intent `atencion.necesito-atencion` en clara |
| `IntentClassifier` en raíz (opcional) | 1ª IA elige; classifier como fallback staff |
| `kind: message` | `interactive` sin botones |

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Catálogo staff muy grande | Summaries cortos; context cache; monitoreo tokens |
| IA devuelve intent sin permiso | Filtrar `intent_ids` contra `UiActionCatalog` |
| Summaries pobres | QA + plantilla + ejemplos en intents |
| 1 intent pero desambiguación interna | PHP respeta `remediation` del motor antes de flow |

## Relación con reglas del proyecto

- Comportamiento particular en metadata (summaries, prompt) y dominio (loaders, RBAC), no regex en orquestador.
- Loaders y maestros en BD + PHP; no listas de hechos en YAML del preprocess.
- Integridad clínica y gates siguen en Yii / `SubIntentEngine`, no en el JSON de la IA.
