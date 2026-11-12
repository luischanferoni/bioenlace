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

El peso de cada componente está en [resumen-costos-bioenlace.md § Composición del COGS](../resumen-costos-bioenlace.md#composición-del-cogs-qué-pesa-en-el-costo). Ordenado por impacto sobre el COGS ambulatorio (**0,00866/atención**, con infra app):

| Palanca | Componente y peso | Efecto estimado / atención | Detalle |
|---------|-------------------|----------------------------|---------|
| Batch API en motivos + codificación (donde la latencia lo permita) | Motivos + captura **15,0 %** | **−0,00065** (−50 % de esas llamadas) | `care-pack-*` seguro; motivos si el resumen se genera con horas de anticipación; codificación si se desacopla del guardado — [costos-api §2](../costos-api.md#2-motivos-de-consulta-cuestionario-y-mensajes-antes-de-la-atención) |
| Recortar tokens de salida (output cuesta 4× input) | Bloque IA (chat + motivos + captura) | **−0,0003 a −0,0009** si se recorta 10–15 % | JSON con valores cortos; sin repetir texto |
| Recalibrar el presupuesto de chat a lo medido (0,0048 → ~0,0038) | Chat **55,4 %** | **−0,0010** (−11,5 %) | **Requiere telemetría**; hoy no se puede medir — [costos-api §1](../costos-api.md#1-conversación-con-el-paciente-chat-asistente) |
| Archivo de video: 1 pista y audio+transcript en frío | Add-on video **57,1 %** | **−0,0012** o más; lo decide la política de archivo, no la retención caliente | [videollamadas.md](videollamadas.md) |
| Fusionar análisis + codificación en una sola llamada | Captura IA **8,1 %** | **−0,0001** (riesgo de calidad; medir antes) | [costos-api §4](../costos-api.md#4-captura-clínica-encounter) |

**Sin telemetría ni cambio de producto, lo medible hoy es batch + tokens de salida: ~0,0011/atención (0,00866 → ~0,0076, −12 %).** Sumando la recalibración del chat (cuando haya datos) llegaría a **~0,0066 (−24 %)**.

Dos palancas **descartadas por ahora**:

- **Dictado de 4 min**: se mantiene en **5 min**. Bajar a 4 requiere validar que el dictado no pierda contenido clínico; no es un ajuste de modelo.
- **Caché de aplicación**: solo ayuda con requests idénticos, y en chat clínico eso es raro. La caché confiable es el **context caching** de Vertex sobre el prefijo estable del prompt, que se mide con `cachedContentTokenCount`.

Bajar COGS **no cambia el margen porcentual**: con precio = COGS × 3,33, cambia el **precio absoluto**. Si la estrategia es que el usuario pague lo mínimo, eso es exactamente lo que se busca; el límite es cubrir los costos fijos a la escala alcanzada.

**Motivos ya no aporta palancas de STT**: el flujo es un cuestionario con opciones (sin audio) y su único costo es la IA del lote — [motivos-consulta.md](motivos-consulta.md).

## Relacionado

- [pruebas-costos-ia.md](../pruebas-costos-ia.md) — conversaciones simuladas y `AICostTracker`
- [infra-estrategias.md](../infra-estrategias.md) — mismas ideas cuando la IA corre en GPU propia
