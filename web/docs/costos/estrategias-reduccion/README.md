# Estrategias de reducción de costo (API e infra)

Palancas para bajar el gasto **por debajo** del costo de referencia en [costos-api.md](../costos-api.md). Esas cifras base incluyen solo lo que modelamos con confianza (p. ej. **context caching de Vertex** en la columna «con caché»). El resto vive acá y **no** se suma automáticamente al COGS de [impuestos-argentina.md](../impuestos-argentina.md) hasta validarlo en producción.

## Índice

| Documento | Tema |
|-----------|------|
| [matriz-casos-uso.md](./matriz-casos-uso.md) | Casos de producto × implícito / explícito / caché app (alineado al código) |
| [estrategias-api.md](./estrategias-api.md) | Resumen por área y enlaces |
| [context-caching-implicita.md](./context-caching-implicita.md) | Context caching implícito (Vertex / Gemini) |
| [context-caching-explicita.md](./context-caching-explicita.md) | Context caching explícito (API `cachedContent`) |
| [cache-aplicacion.md](./cache-aplicacion.md) | Caché Yii por hash (evita llamadas) |
| [uso-condicional-ia.md](./uso-condicional-ia.md) | Reglas / CPU antes de IA |
| [proveedor-modelo-tokens.md](./proveedor-modelo-tokens.md) | Proveedor, modelo, límites de tokens |
| [motivos-consulta.md](./motivos-consulta.md) | Lote pre-atención |
| [pre-consulta-onboarding.md](./pre-consulta-onboarding.md) | Conversación con el paciente (`user_goal`) y onboarding |
| [stt.md](./stt.md) | Transcripción de audio |
| [vision.md](./vision.md) | Análisis de imágenes |
| [videollamadas.md](./videollamadas.md) | Video paciente–médico |
| [monitoreo.md](./monitoreo.md) | Métricas, tracker, pruebas |

## Relacionado

- [pruebas-costos-ia.md](../pruebas-costos-ia.md) — conversaciones simuladas y `AICostTracker`
- [infra-estrategias.md](../infra-estrategias.md) — mismas ideas cuando la IA corre en GPU propia
