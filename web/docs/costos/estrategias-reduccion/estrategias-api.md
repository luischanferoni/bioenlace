# Estrategias de reducción de costos – API (índice)

Resumen de palancas para quedar **por debajo** de [costos-api.md](../costos-api.md). El COGS fiscal en [impuestos-argentina.md](../impuestos-argentina.md) usa solo las columnas **sin / con context caching** de Gemini; el resto está acá hasta validarlo.

## Resumen por área

| Área | Costo ref. | Reducción orientativa | Documento |
|------|------------|----------------------|-----------|
| IA – context caching implícito | Incluido en columna «con caché» | ~26 % en IA+STT intensivo | [context-caching-implicita.md](./context-caching-implicita.md) |
| IA – context caching explícito | No en COGS base | Variable; requiere implementación | [context-caching-explicita.md](./context-caching-explicita.md) |
| IA – caché aplicación | No en COGS base | 40–60 % si hay repeats | [cache-aplicacion.md](./cache-aplicacion.md) |
| IA – uso condicional | No en COGS base | 30–50 % | [uso-condicional-ia.md](./uso-condicional-ia.md) |
| IA – proveedor / tokens | Comparativa Together en costos-api; **modelo por contexto** (subir solo `motivos-consulta-insights`) | 20–50 % / variable | [proveedor-modelo-tokens.md](./proveedor-modelo-tokens.md) |
| Motivos consulta | ~$0,42/prof (audio) | Variable | [motivos-consulta.md](./motivos-consulta.md) |
| Conversación paciente / onboarding | ~$0,47 + ~$0,14 (§1–3 costos-api) | 30–60 % | [pre-consulta-onboarding.md](./pre-consulta-onboarding.md) |
| STT | ~$0,28 (Groq ref., COGS servidor) | Variable con STT en dispositivo + fallback por calidad; evolución: modelo fit (base + LoRA provincia + speaker) | [stt.md](./stt.md) |
| Vision | $0 en ref. | 50–100 % | [vision.md](./vision.md) |
| Videollamadas | ~$11,52 Twilio | 20–50 % | [videollamadas.md](./videollamadas.md) |
| Monitoreo | — | — | [monitoreo.md](./monitoreo.md) |

## Infra propia

Si la IA corre en GPU propia: [infra-estrategias.md](../infra-estrategias.md) (menos llamadas = menos carga).

## Parámetros

`web/frontend/config/params.php` — proveedor, modelo (`vertex_ai_model`), cachés, `ia_usage_tracking_habilitado`.

Índice de contextos IA: [producto/catalogo-usos-ia.md](../../producto/catalogo-usos-ia.md).
