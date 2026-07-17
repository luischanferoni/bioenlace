# Estrategias de reducción de costos – API (índice)

Resumen de palancas para quedar **por debajo** de [costos-api.md](../costos-api.md). El COGS fiscal en [impuestos-argentina.md](../impuestos-argentina.md) usa solo las columnas **sin / con context caching** de Gemini; el resto está acá hasta validarlo.

## Resumen por área

| Área | Costo ref. | Reducción orientativa | Documento |
|------|------------|----------------------|-----------|
| IA – context caching implícito | Incluido en columna «con caché» | ~26 % en IA+STT intensivo | [context-caching-implicita.md](./context-caching-implicita.md) |
| IA – context caching explícito | No en COGS base | Variable; requiere implementación | [context-caching-explicita.md](./context-caching-explicita.md) |
| IA – caché aplicación | No en COGS base | 40–60 % si hay repeats | [cache-aplicacion.md](./cache-aplicacion.md) |
| IA – uso condicional | No en COGS base | 30–50 % | [uso-condicional-ia.md](./uso-condicional-ia.md) |
| IA – proveedor / tokens | Comparativa DeepSeek en costos-api; **modelo por contexto** (subir solo `motivos-consulta-insights`) | 5–15 % / variable | [proveedor-modelo-tokens.md](./proveedor-modelo-tokens.md) |
| Motivos consulta | ~$0,42/prof (audio) | Variable | [motivos-consulta.md](./motivos-consulta.md) |
| Conversación paciente / onboarding | ~$0,47 + ~$0,14 (§1–3 costos-api) | 30–60 % | [pre-consulta-onboarding.md](./pre-consulta-onboarding.md) |
| STT / **Edge-Cloud Routing** | ~$2,52 (Groq ref., §2 ~4 min + §4 ~5 min) | Dispositivo primero, servidor solo fallback; evolución: modelo fit on-device | [stt.md](./stt.md#edge-cloud-routing-stt) |
| Vision | $0 en ref. | 50–100 % | [vision.md](./vision.md) |
| Videollamadas | COGS planificado **$5,00** (self-host; STT en §2/§4) | Límite duración; autoescalado SFU; retención storage | [videollamadas.md](./videollamadas.md) |
| Monitoreo | — | — | [monitoreo.md](./monitoreo.md) |

## Infra propia

Si la IA corre en GPU propia: [infra-estrategias.md](../infra-estrategias.md) (menos llamadas = menos carga).

## Parámetros

`web/frontend/config/params.php` — proveedor, modelo (`vertex_ai_model`), cachés, `ia_usage_tracking_habilitado`.

Índice de contextos IA: [producto/catalogo-usos-ia.md](../../producto/catalogo-usos-ia.md).
