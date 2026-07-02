# Proveedor, modelo y tokens

Catálogo de contextos: [producto/catalogo-usos-ia.md](../../producto/catalogo-usos-ia.md).

## Proveedor

`ia_proveedor` en params: `google` (Vertex/Gemini), `huggingface`, `groq`, `openai`, `ollama`.

Producción Bioenlace: **Google** con **`gemini-2.5-flash-lite`** (global en `vertex_ai_model`). **DeepSeek V4 Flash** aparece en [costos-api.md](../costos-api.md) como comparativa de precio (API directa).

## Modelo por contexto (diferencial)

Hoy un solo modelo para todos los contextos `IAManager`. Palanca futura: `vertex_ai_model_by_context` en params (sin hardcode por pantalla).

| Contexto (`IAManager`) | Modelo sugerido | Dirección |
|------------------------|-----------------|-----------|
| `asistente-preprocess` | Flash Lite | Mantener (alto volumen) |
| `asistente-conversational` | Flash Lite | Mantener; ahorro = menos llamadas ([pre-consulta-onboarding](./pre-consulta-onboarding.md)) |
| `intent-engine-classification` | Flash Lite | Mantener; ahorro = más reglas ([uso-condicional-ia](./uso-condicional-ia.md)) |
| `motivos-consulta-batch` | Flash Lite | Mantener |
| **`motivos-consulta-insights`** | **2.5 Pro o 2.5 Flash** | **Subir** si se prioriza calidad de sugerencias clínicas (1×/consulta; impacto COGS acotado) |
| `analisis-consulta` | Flash Lite | Mantener salvo piloto con muchos fallos de extracción |
| `encounter-codificacion-automatica` | Flash Lite | Mantener; calidad crítica → piloto con 2.5 Flash antes de subir modelo global |

**Subir modelo** solo donde el producto exige más razonamiento clínico; el resto del catálogo ya está en el tier económico de Gemini — ver tarifas en [costos-api § Gemini Flash](../costos-api.md#gemini-flash-tarifas-actuales-y-context-caching).

## Limitar tokens

- `vertex_ai_max_tokens`, `google_max_output_tokens`, `hf_max_length`
- Prompts acotados en entrypoints del asistente

Reducción orientativa: **10–30 %** por llamada.

## Compresión en tránsito

`comprimir_datos_transito` — impacto menor en coste total.
