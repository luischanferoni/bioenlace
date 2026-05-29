# Proveedor, modelo y tokens

## Proveedor

`ia_proveedor` en params: `google` (Vertex/Gemini), `huggingface`, `groq`, `openai`, `ollama`.

Producción Bioenlace: **Google** con **`gemini-2.5-flash-lite`**. Together AI aparece en [costos-api.md](../costos-api.md) solo como comparativa de precio.

## Limitar tokens

- `vertex_ai_max_tokens`, `google_max_output_tokens`, `hf_max_length`
- Prompts acotados en entrypoints del asistente

Reducción orientativa: **10–30 %** por llamada.

## Compresión en tránsito

`comprimir_datos_transito` — impacto menor en coste total.
