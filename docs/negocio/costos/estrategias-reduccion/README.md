# Estrategias de reducción de costo (API e infra)

Palancas para bajar el gasto **por debajo** del costo de referencia en [costos-api.md](../costos-api.md) ([COGS](../costos-api.md#cogs-abreviatura): costo variable directo de APIs por uso). Esas cifras base incluyen solo lo que modelamos con confianza (p. ej. **context caching de Vertex** en la columna «con caché»). El resto vive acá y **no** se suma automáticamente al COGS de [impuestos-argentina.md](../impuestos-argentina.md) hasta validarlo en producción.

## Índice

| Documento | Tema |
|-----------|------|
| [matriz-casos-uso.md](matriz-casos-uso.md) | Casos de producto × implícito / explícito / caché app (alineado al código) |
| [estrategias-api.md](estrategias-api.md) | Resumen por área y enlaces |
| [context-caching-implicita.md](context-caching-implicita.md) | Context caching implícito (Vertex / Gemini) |
| [context-caching-explicita.md](context-caching-explicita.md) | Context caching explícito (API `cachedContent`) |
| [cache-aplicacion.md](cache-aplicacion.md) | Caché Yii por hash (evita llamadas) |
| [uso-condicional-ia.md](uso-condicional-ia.md) | Reglas / CPU antes de IA |
| [proveedor-modelo-tokens.md](proveedor-modelo-tokens.md) | Proveedor, modelo por contexto (subir `motivos-consulta-insights`), tokens |
| [motivos-consulta.md](motivos-consulta.md) | Lote pre-atención |
| [pre-consulta-onboarding.md](pre-consulta-onboarding.md) | Conversación con el paciente (`user_goal`) y onboarding |
| [stt.md](stt.md) | Transcripción de audio |
| [vision.md](vision.md) | Análisis de imágenes |
| [videollamadas.md](videollamadas.md) | Video paciente–médico |
| [monitoreo.md](monitoreo.md) | Métricas, tracker, pruebas |

## Prioridad de palancas (según el mix del COGS)

El peso de cada componente está en [resumen-costos-bioenlace.md § Composición del COGS](../resumen-costos-bioenlace.md#composición-del-cogs-qué-pesa-en-el-costo). Ordenado por impacto sobre el COGS ambulatorio (0,0085/atención):

| Palanca | Componente y peso | Efecto estimado / atención | Detalle |
|---------|-------------------|----------------------------|---------|
| Recalibrar el presupuesto de chat a lo medido (0,0048 → ~0,0038) | Chat **56,5 %** | **−0,0010** (−12 % del COGS AMB) | [costos-api §1](../costos-api.md#1-conversación-con-el-paciente-chat-asistente) — decisión de pricing, no de producto |
| Activar caché de aplicación y dedup (hoy desactivados por param) | Chat | evita llamadas repetidas; hit rate alto solo en contextos repetitivos | [cache-aplicacion.md](cache-aplicacion.md), [uso-condicional-ia.md](uso-condicional-ia.md) |
| Más cobertura de STT on-device y dictado de 4 min | Dictado **29,4 %** · EMER/IMP **~68 %** | **−0,0005** por los 4 min; hasta −30 % del STT si sube la cobertura on-device | [stt.md](stt.md) |
| Archivo de video: 1 pista y audio+transcript en frío | Add-on video **57,1 %** | **−0,0012** o más; lo decide la política de archivo, no la retención caliente | [videollamadas.md](videollamadas.md) |
| Fusionar análisis + codificación en una sola llamada | Captura IA **7,1 %** | **−0,0001** (riesgo de calidad; medir antes) | [costos-api §4](../costos-api.md#4-captura-clínica-encounter) |

Acumulado realista en ambulatorio: **~0,0015/atención** (COGS **0,0085 → 0,0070**, −18 %). Bajar COGS no cambia el margen porcentual: cambia el precio absoluto.

**Motivos ya no aporta palancas de STT**: el flujo es un cuestionario con opciones (sin audio) y su único costo es la IA del lote — [motivos-consulta.md](motivos-consulta.md).

## Relacionado

- [pruebas-costos-ia.md](../pruebas-costos-ia.md) — conversaciones simuladas y `AICostTracker`
- [infra-estrategias.md](../infra-estrategias.md) — mismas ideas cuando la IA corre en GPU propia
