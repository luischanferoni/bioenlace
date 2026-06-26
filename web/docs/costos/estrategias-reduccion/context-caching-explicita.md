# Context caching explícito (Vertex / Gemini)

## Qué es

Sigue siendo el **servicio de IA de Google**, pero **vos definís y cargás** la caché:

1. `POST .../cachedContents` con un bloque grande y estable.
2. En cada `generateContent`, referenciás ese recurso + enviás solo la parte variable.

Los tokens del bloque cacheado se facturan a **~10 %** del precio de input en Gemini 2.5+ (90 % de descuento sobre esa porción).

| | Implícito | Explícito (API) | Simulado local |
|---|-----------|-----------------|----------------|
| Alta en API | No | Sí (`cachedContent`) | No (misma estructura de payload) |
| Estado en Bioenlace | Operativo vía proveedor | **Pendiente** | **`VertexContextCacheSimulator`** (`vertex_context_cache_simulado`) |

## Simulación local (implementado)

`web/common/components/Platform/Ai/Providers/Google/VertexContextCacheSimulator.php`:

- Parte **estable** → `systemInstruction` (instrucciones, esquema JSON, reglas).
- Parte **variable** → `contents[0].parts` (mensaje del usuario, transcript, etc.).
- Registra entradas en memoria y, si `usageMetadata.cachedContentTokenCount` es 0, estima tokens cacheados en `AICostTracker` para calibrar el escenario favorable del doc.

Contextos con split dedicado: `asistente-preprocess`, `asistente-conversational`; candidatos por bloque estable largo: `analisis-consulta`, `encounter-codificacion-automatica`, `motivos-consulta-batch`. Ver [catálogo de IA](../../producto/catalogo-usos-ia.md).

Integración: `IAManager::consultarIA` antes del POST a Google.

## Por qué la API explícita real sigue pendiente

1. **Tamaño**: muchos prompts son cortos (&lt; 2.048 tokens); hace falta centralizar instrucciones + catálogo en un bloque estable.
2. **Coste de almacenamiento**: conviene solo si el bloque se reutiliza **muchas veces por hora**.
3. **Invalidación**: al cambiar `ui_json`, permisos o schemas de intents.

## Diseño recomendado (API real)

```
┌─────────────────────────────────────┐
│ Bloque cacheado (≥2048 tok, TTL)    │
│  cachedContent.name                 │
└─────────────────────────────────────┘
           +
┌─────────────────────────────────────┐
│ Parte variable por request          │
└─────────────────────────────────────┘
```

Pasos: crear `cachedContents` → referenciar en `generateContent` → invalidar al cambiar políticas del efector.

## Relación con costos-api.md

- **COGS base:** columna **sin caché**.
- **Escenario favorable:** columna **con caché** (~**25 %** input cacheado en §2–4; §1 con % por tipo de llamada).
- La simulación local sirve para medir si conviene invertir en `cachedContents` real.

## Referencias

- [Create context cache](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-create)
- [context-caching-implicita.md](./context-caching-implicita.md)
