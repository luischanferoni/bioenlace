# Context caching explícito (Vertex / Gemini)

## Qué es

Vos **creás** un recurso de caché en la API de Google (`cachedContents.create`) con un bloque grande y estable (system prompt, políticas del efector, esquemas YAML, fragmentos de catálogo). En cada `generateContent` referenciás ese recurso; los tokens del bloque se facturan a **~10 %** del precio de input en Gemini 2.5+ (90 % de descuento sobre esa porción).

| | Implícito | Explícito |
|---|-----------|-----------|
| Alta en API | No | Sí (`cachedContent` / `cached_content`) |
| Mínimo de tokens | Prefijo repetido (sin mínimo fijo nuestro) | **~2.048** tokens en 2.0/2.5 |
| Coste de almacenamiento | No | **$/M tokens·hora** (p. ej. 2.5 Flash Lite: ver [pricing](https://cloud.google.com/vertex-ai/generative-ai/pricing)) |
| Control del TTL | Google | Vos definís TTL al crear la caché |
| Estado en Bioenlace | **Operativo** vía proveedor (medible) | **No implementado** en código |

## Por qué aún no está en el producto

1. **Tamaño**: hoy muchos prompts del asistente son cortos (&lt; 2.048 tokens). Hasta no centralizar instrucciones + semántica de intents + contexto de efector en un bloque estable, el mínimo de Google no se alcanza.
2. **Coste de almacenamiento**: conviene solo si el bloque se reutiliza **muchas veces por hora** (mismo efector / misma sesión de guardia).
3. **Implementación**: requiere servicio que cree/renueve cachés, invalide al cambiar políticas del efector y pase `cachedContent` en el payload (distinto del envío actual de un solo `user` con todo el texto).

## Diseño recomendado (cuando implementemos)

```
┌─────────────────────────────────────┐
│ Bloque cacheado (≥2048 tok, TTL)    │  ← instrucciones asistente, reglas RBAC,
│  cachedContent.name                 │    catálogo acotado por efector
└─────────────────────────────────────┘
           +
┌─────────────────────────────────────┐
│ Parte variable por request          │  ← mensaje usuario, TOON, transcript
└─────────────────────────────────────┘
```

Pasos técnicos (Vertex REST):

1. `POST .../cachedContents` con `model`, `contents`, `ttl` / `expireTime`.
2. En `generateContent`, incluir referencia al `name` del recurso + contents variables.
3. Invalidar caché cuando cambien `ui_json`, permisos o versión de schemas de intents.

Código actual: un solo `contents[]` con rol `user` y texto completo (`ProviderPromptAssigner`). Habría que extender el proveedor `google` para soportar `cachedContent` + parts variables.

## Cuándo priorizarlo

- Mismo efector con **alto volumen** de clasificación / chat en ventana corta.
- Bloque estable ya supera **2.048 tokens** (p. ej. catálogo de intents + `intent_semantics` embebido).
- El `ratio_input_en_cache` del tracker **implícito** ya es alto pero queremos **garantizar** el prefijo y el TTL.

## Relación con costos-api.md

La columna **«con caché»** del presupuesto base usa el **escenario aritmético** (80 % input cacheado), alineado con tarifa explícita/implícita de 2.5 Flash Lite. **No implica** que la API explícita ya esté activa.

## Referencias

- [Create context cache](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-create)
- [context-caching-implicita.md](./context-caching-implicita.md)
