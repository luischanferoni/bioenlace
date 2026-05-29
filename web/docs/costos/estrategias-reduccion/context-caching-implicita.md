# Context caching implícito (Vertex / Gemini)

## Qué es

Mecanismo de **Google** (sin llamada extra nuestra) que detecta **prefijos de entrada repetidos** entre solicitudes recientes al mismo modelo y factura esos tokens a **tarifa reducida** (`cachedContentTokenCount` en `usageMetadata`).

- **No evita** la llamada al modelo: solo abarata parte del **input**.
- **No requiere** crear un recurso `cachedContent` en la API.
- En la documentación de costos de Bioenlace, la columna **«con caché»** de [costos-api.md](../costos-api.md) se basa en este mecanismo (más el modelo **`gemini-2.5-flash-lite`** configurado en servidor).

## Alineación con Bioenlace

| Aspecto | Estado |
|---------|--------|
| Modelo en producción | `vertex_ai_model` → **`gemini-2.5-flash-lite`** (`params.php`) |
| Integración explícita en código | **No** hace falta para el implícito; Google aplica hits cuando el prefijo coincide |
| Medición | `AICostTracker` + `ia_usage_tracking_habilitado` lee `usageMetadata.cachedContentTokenCount` por contexto |

## Cuándo ayuda en nuestros flujos

Funciona mejor si el **inicio del prompt es idéntico** entre llamadas y solo cambia el final (mensaje del usuario, TOON, etc.):

| Contexto (`IAManager`) | Prefijo estable | Comentario |
|------------------------|-----------------|------------|
| `asistente-preprocess` | Instrucciones + reglas JSON | Buen candidato |
| `asistente-conversational` | Bloque fijo + «Usuario:» | Buen candidato entre mensajes |
| `intent-engine-classification` | TOON variable **al inicio** tras pocas líneas | Candidato **débil** |
| `motivos-consulta-batch` | Plantilla corta + transcript único | Poco % cacheable |

El supuesto de presupuesto en costos-api (**~80 % del input en caché** en escenario intensivo) es un **objetivo de diseño** cuando el asistente lleve system + reglas + catálogo al inicio del prompt; hay que **calibrarlo** con `ratio_input_en_cache` del tracker.

## Cómo validar

1. En staging: `ia_usage_tracking_habilitado => true` en `web/frontend/config/params.php`.
2. Tráfico real o conversaciones de prueba contra Gemini (no simuladas).
3. Revisar `getResumen()['tokens']['ratio_input_en_cache']` y desglose `por_contexto`.
4. Comparar con factura Vertex (línea de tokens cacheados).

## Referencias

- [Vertex – Context caching overview](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-overview)
- [costos-api.md – Gemini Flash](../costos-api.md#gemini-flash-tarifas-actuales-y-context-caching)
