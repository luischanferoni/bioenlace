# Context caching implícito (Vertex / Gemini)

## Qué es

Mecanismo del **servicio de IA de Google** (Vertex / Gemini): detecta **prefijos de entrada repetidos** entre solicitudes recientes y factura esa porción a **tarifa reducida** (`cachedContentTokenCount` en `usageMetadata`).

- **Bioenlace no “activa” nada especial** salvo usar el mismo modelo y repetir el inicio del prompt.
- **No evita** la llamada al modelo: solo abarata parte del **input**.
- **No creás** un recurso de caché en la API (eso es la [explícita](./context-caching-explicita.md)).
- La columna **«con context caching»** de [costos-api.md](../costos-api.md) modela un escenario **favorable** (~**25 %** input cacheado en §2–4); **COGS base = columna sin caché**.

## Alineación con Bioenlace

| Aspecto | Estado |
|---------|--------|
| Modelo en producción | `vertex_ai_model` → **`gemini-2.5-flash-lite`** (`params.php`) |
| Estructura de prompt | Preprocess y conversacional: **instrucciones al inicio**, mensaje al final |
| Medición | `AICostTracker` + `ia_usage_tracking_habilitado`; complemento con `vertex_context_cache_simulado` |

## Cuándo ayuda en nuestros flujos

Ver [matriz-casos-uso.md](./matriz-casos-uso.md). Resumen conservador:

| Contexto (`IAManager`) | % input cacheado (supuesto doc) |
|------------------------|----------------------------------|
| `asistente-preprocess` | ~**40 %** |
| `asistente-conversational` | ~**50 %** |
| `motivos-consulta-batch` | ~**25 %** |
| `analisis-consulta` | ~**25 %** |

No usar ratios altos (p. ej. 80 %) sin telemetría. Calibrar con `ratio_input_en_cache` por `contexto`.

## Cómo validar

1. `ia_usage_tracking_habilitado => true` y opcional `vertex_context_cache_simulado => true` en `web/frontend/config/params.php`.
2. Tráfico real o conversaciones de prueba contra Gemini.
3. Revisar `getResumen()['tokens']` y desglose `por_contexto`.
4. Comparar con factura Vertex.

## Referencias

- [Vertex – Context caching overview](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-overview)
- [costos-api.md – Gemini Flash](../costos-api.md#gemini-flash-tarifas-actuales-y-context-caching)
