# Estrategias de reducción de costos

Este documento detalla **formas de reducir costos** y estima en **qué porcentaje se puede reducir el costo real** de cada ítem. El **costo real** (sin aplicar estas estrategias) está documentado en [COSTOS.md](./COSTOS.md). Todas las reducciones que se indican aquí son **porcentajes sobre ese costo real**.

---

## Resumen por área: costo real (ref.) y reducción estimada

| Área | Costo real de referencia (COSTOS.md) | Reducción estimada (% del costo real) | Principales palancas |
|------|--------------------------------------|----------------------------------------|-----------------------|
| IA / modelos (chat, corrección, análisis) | Incluido en costo por consulta del plan (ej. \$0.014/consulta RunPod) | **40–60%** | Caché, uso condicional, proveedor/modelo más barato |
| Infra GPU / hosting | \$8.36/médico/mes (RunPod) a \$1.40–4.56 (Spot/Preemptible); \$0.014/consulta (RunPod) | **43–88%** según plan y optimizaciones | Optimizaciones de código, elección de plan, volumen |
| Conversación pre-consulta | \$13.50–21/médico/mes | **30–50%** | Menos % que llama IA, respuestas predefinidas, caché |
| Agente onboarding | \$3.60–5.60/médico/mes | **Hasta ~60%** | Flujos guiados, FAQ, caché |
| Medios (STT + Vision) | \$8.95–9.60/médico/mes (uso máximo) | **50–100%** | Transcribir/analizar solo cuando aporte; tier gratis; caché |
| Videollamadas | \$10–17.30/médico/mes | **20–50%** | Plan por asiento, límite de duración, proveedor |

---

## Rango de reducción total (ponderado por costo real)

Los porcentajes anteriores aplican a **cada ítem por separado**. Para estimar **cuánto se podría reducir el costo total por médico** hay que ponderar por el peso de cada ítem en el costo real. A continuación se usa el ejemplo de [COSTOS.md](./COSTOS.md): RunPod + todas las capacidades ≈ **\$62/médico/mes** (costo real total).

| Ítem | Costo real (USD/médico/mes) | Reducción baja | Reducción alta | Ahorro bajo | Ahorro alto |
|------|-----------------------------|----------------|----------------|-------------|-------------|
| Infra GPU / hosting | 8.36 | 43% | 88% | 3.60 | 7.36 |
| Conversación pre-consulta | 21.00 | 30% | 50% | 6.30 | 10.50 |
| Agente onboarding | 5.60 | 20% | 60% | 1.12 | 3.36 |
| Medios (STT + Vision) | 9.60 | 50% | 100% | 4.80 | 9.60 |
| Videollamadas | 17.30 | 20% | 50% | 3.46 | 8.65 |
| **Total** | **61.86** | — | — | **19.28** | **39.47** |

- **Reducción total (escenario conservador)**: 19.28 ÷ 61.86 ≈ **31%** del costo real total.
- **Reducción total (escenario agresivo)**: 39.47 ÷ 61.86 ≈ **64%** del costo real total.

### Rango promedio de reducción total

Aplicando las estrategias de este documento de forma combinada, el **costo total por médico/mes** podría bajar, en promedio, entre **30% y 65%** del costo real (según qué tan conservador o agresivo sea el uso de cada estrategia). En el ejemplo:

- **Costo real total**: ~\$62/médico/mes.
- **Costo estimado tras estrategias (conservador)**: ~\$43/médico/mes (~31% menos).
- **Costo estimado tras estrategias (agresivo)**: ~\$22/médico/mes (~64% menos).

*Nota*: Los porcentajes por ítem no se suman de forma directa porque cada uno aplica a una base distinta. La tabla anterior hace la **suma ponderada** (ahorro en USD por ítem) y luego calcula el % sobre el total.

---

## 1. IA y modelos (chat, corrección, análisis médico)

### 1.1 Caché de respuestas y resultados

- **Qué hace**: Evitar llamadas repetidas al modelo para consultas o correcciones muy similares.
- **Dónde aplica**: Respuestas del chat, corrección de texto médico, análisis, embeddings, transcripciones (STT).
- **Cómo**: TTL largos en configuración (`ia_cache_ttl`, `correccion_cache_ttl`, `embedding_cache_ttl`, `stt_cache_ttl` en `params.php`). Clave de caché basada en hash del input (y opcionalmente usuario/contexto).
- **Reducción estimada sobre el costo real**: **40–60%** del costo de IA (referencia: costo por consulta / por llamada en [COSTOS.md](./COSTOS.md)). En escenarios con muchas consultas repetidas o similares y TTL de 7–30 días el ahorro es significativo.
- **Riesgo**: Respuestas algo desactualizadas si el modelo o las guías cambian; mitigable con TTL más cortos en entornos de prueba.

### 1.2 Uso condicional de IA (no llamar siempre)

- **Qué hace**: Usar reglas, diccionarios o flujos predefinidos primero; llamar al modelo solo cuando sea necesario.
- **Dónde aplica**: Corrección de texto médico (SymSpell + diccionario + IA condicional, ver [FLUJO_CORRECCION_TEXTO_MEDICO.md](./FLUJO_CORRECCION_TEXTO_MEDICO.md)), clasificación de intents del chat (reglas/keywords primero, IA como fallback), respuestas FAQ.
- **Cómo**: Mantener el flujo híbrido actual; extenderlo a más intents con reglas claras antes de invocar IA.
- **Reducción estimada sobre el costo real**: **30–50%** del costo de IA (referencia: [COSTOS.md](./COSTOS.md)). En corrección de texto el ahorro es muy claro; en chat depende de la calidad de las reglas.

### 1.3 Elección de proveedor y modelo

- **Qué hace**: Usar el proveedor y modelo más baratos que cumplan calidad mínima.
- **Opciones**: `ia_proveedor` en `params.php`: `google` (Vertex/Gemini), `huggingface`, `groq`, `openai`, `ollama`. Para Gemini: `gemini-1.5-flash` es más barato que `gemini-1.5-pro`. HuggingFace tiene tier gratuito (30K requests/mes).
- **Cómo**: Activar `hf_use_free_tier` si se usa HuggingFace; probar Gemini Flash para flujos no críticos; considerar Groq para latencia baja a costo moderado.
- **Reducción estimada sobre el costo real**: **20–50%** del costo de IA (referencia: [COSTOS.md](./COSTOS.md)) según cambio de modelo/proveedor (ej. Pro → Flash, o maximizar tier gratuito).

### 1.4 Limitar tokens y complejidad

- **Qué hace**: Reducir `max_tokens` / `maxOutputTokens` y longitudes de prompt para no pagar de más.
- **Dónde**: `vertex_ai_max_tokens`, `google_max_output_tokens`, `hf_max_length` en params; prompts acotados en el código.
- **Cómo**: Fijar un máximo razonable por tipo de tarea (ej. 500–1000 para respuestas cortas); truncar contexto muy largo antes de enviar al modelo.
- **Reducción estimada sobre el costo real**: **10–30%** del costo de IA por llamada (referencia: [COSTOS.md](./COSTOS.md)) según recorte de tokens y contexto.

### 1.5 Comprimir datos en tránsito

- **Qué hace**: Enviar menos bytes al proveedor (gzip) donde el API lo acepte.
- **Dónde**: `comprimir_datos_transito` en params; IAManager (algunos proveedores no aceptan compresión).
- **Cómo**: Mantener activo para proveedores compatibles; no comprimir para HuggingFace si da 422.
- **Reducción estimada sobre el costo real**: Menor ancho de banda y en algunos proveedores menor facturación por request; **impacto variable** (no suele ser el mayor % del ahorro).

---

## 2. Infraestructura GPU / hosting

### 2.1 Aplicar todas las optimizaciones de código

- **Qué hace**: Las optimizaciones documentadas (caché, uso condicional, procesamiento selectivo, etc.) reducen el número de llamadas y la carga por consulta, lo que permite menos instancias o instancias más baratas.
- **Dónde**: Parámetros en `params.php` (`usar_cpu_tareas_simples`, cachés, etc.); flujo híbrido en corrección de texto.
- **Reducción estimada sobre el costo real**: **40–60%** del costo de infra/hosting (referencia: [COSTOS.md](./COSTOS.md), planes por consulta/médico). Ejemplo: costo real RunPod \$8.36/médico/mes → con optimizaciones puede bajar a \$3–5/médico/mes.

### 2.2 Elegir el plan de hosting adecuado

- **Qué hace**: Comparar RunPod (precio fijo, estable), AWS Reserved (descuento por compromiso), AWS Spot y GCP Preemptible (más baratos pero con riesgo de interrupciones).
- **Cómo**: Producción crítica → RunPod o AWS Reserved. Pruebas o cargas tolerantes a cortes → Spot/Preemptible con fallback o reintentos.
- **Reducción estimada sobre el costo real**: **43–88%** del costo de hosting (referencia: [COSTOS.md](./COSTOS.md), tabla comparativa) si se pasa de RunPod (costo real \$8.36/médico/mes) a Spot/Preemptible (costo real \$1.40–4.56), a costa de estabilidad.

### 2.3 Economías de escala

- **Qué hace**: A mayor volumen de consultas por médico (o por instancia), el costo por consulta baja cuando el costo mensual del plan es fijo (RunPod).
- **Cómo**: Agrupar médicos en la misma infra; dimensionar instancias para un uso medio-alto sin sobredimensionar.
- **Reducción estimada sobre el costo real**: No es % de reducción del costo total mensual del plan, sino **menor costo por consulta** (referencia: [COSTOS.md](./COSTOS.md), escenarios por volumen). El costo real por consulta baja al aumentar consultas/mes si el plan es de precio fijo.

---

## 3. Conversación pre-consulta (chat para despejar dudas)

### 3.1 Reducir la fracción de mensajes que llaman a IA

- **Qué hace**: Responder con respuestas predefinidas o flujos guiados para preguntas frecuentes (preparación, documentación, horarios, estado del turno); usar IA solo para preguntas abiertas o no catalogadas.
- **Cómo**: Ampliar keywords/patrones e intents para "pre-consulta"; respuestas tipo plantilla para los más comunes; clasificador por reglas primero (como en el orquestador actual).
- **Reducción estimada sobre el costo real**: **30–40%** del costo real de conversación pre-consulta (referencia: [COSTOS.md](./COSTOS.md), ítem 1 — \$13.50–21/médico/mes). Ejemplo: bajar de 50% a 30% de mensajes con IA reduce el ítem en ~40%.

### 3.2 Caché por pregunta o intención

- **Qué hace**: Cachear respuestas de IA por hash de pregunta (o intent + parámetros) para que preguntas repetidas no vuelvan a llamar al modelo.
- **Cómo**: Mismo mecanismo que `ia_cache_ttl`; clave que incluya texto normalizado o intent + parámetros.
- **Reducción estimada sobre el costo real**: **20–40%** adicional del costo real de pre-consulta ([COSTOS.md](./COSTOS.md), ítem 1) si hay muchas preguntas repetidas entre pacientes.

### 3.3 Límite de mensajes o ventana pre-consulta

- **Qué hace**: Acotar cuántos mensajes o en qué ventana temporal se ofrece el chat pre-consulta (ej. solo 48 h antes del turno), para evitar uso ilimitado.
- **Cómo**: Regla de negocio: después de N mensajes o fuera de ventana, derivar a FAQ o a contacto humano.
- **Reducción estimada sobre el costo real**: **Variable** según límite; reduce el costo real de pre-consulta ([COSTOS.md](./COSTOS.md), ítem 1) de forma directa al reducir número de mensajes.

---

## 4. Agente de IA para onboarding y tareas del día a día

### 4.1 Priorizar flujos guiados y FAQ

- **Qué hace**: Resolver onboarding y preguntas frecuentes con árboles de decisión, botones y respuestas fijas; usar IA solo cuando el usuario hace una pregunta libre no cubierta.
- **Cómo**: Catálogo de pasos de onboarding y FAQ; detección por intent/keyword; IA como fallback.
- **Reducción estimada sobre el costo real**: **Hasta ~60%** del costo real del agente de onboarding (referencia: [COSTOS.md](./COSTOS.md), ítem 2 — \$3.60–5.60/médico/mes). De ~400 a ~150–200 llamadas IA/mes se acerca a ese ahorro.

### 4.2 Caché por tipo de consulta

- **Qué hace**: Cachear respuestas del agente por tipo (ej. "cómo sacar turno", "dónde ver resultados") para que usuarios distintos con la misma duda no generen nueva llamada.
- **Cómo**: TTL de 24–72 h para respuestas de soporte; clave por intent o pregunta normalizada.
- **Reducción estimada sobre el costo real**: **20–40%** del costo real del agente de onboarding ([COSTOS.md](./COSTOS.md), ítem 2).

### 4.3 Reducir interacciones por usuario

- **Qué hace**: Mejorar UX para que el usuario resuelva en menos mensajes (mejor redacción, menos pasos, tooltips en la app).
- **Cómo**: Diseño de flujos y textos; A/B testing de mensajes.
- **Reducción estimada sobre el costo real**: **Variable**; reduce directamente el costo real del agente ([COSTOS.md](./COSTOS.md), ítem 2) al bajar el número de llamadas IA.

---

## 5. Medios (audios, fotos, videos): sin almacenamiento en cloud

En el modelo actual **no se almacenan** audios, fotos ni videos en cloud storage (Google Cloud Storage u otro). Los medios son **vistos y escuchados directamente** por el médico/personal médico en la aplicación; solo se envían a la nube cuando se requiere que la **IA los analice** (Speech-to-Text, Vision API). Ver [CAPACIDADES_PACIENTE_MEDICO.md](./CAPACIDADES_PACIENTE_MEDICO.md) y [COSTOS.md](./COSTOS.md#costos-por-capacidades-adicionales).

### 5.1 No almacenar en cloud (modelo actual)

- **Qué hace**: Elimina por completo el costo de almacenamiento y de egress para medios: **\$0** en GCS u otro objeto storage.
- **Cómo**: Los archivos se transmiten para visualización/escucha en el momento (y pueden quedar en dispositivo o en almacenamiento local/institucional si se define); no se persisten en buckets en la nube.
- **Impacto**: Costo de medios = solo el de STT/Vision cuando se invoque análisis (ver secciones 6 y 7).

### 5.2 Enviar a IA solo cuando aporte valor

- **Qué hace**: Reducir al mínimo las llamadas a Speech-to-Text y Vision API: transcribir o analizar solo cuando el médico o el flujo lo requieran explícitamente.
- **Cómo**: Transcripción y análisis bajo demanda (ej. botón "Transcribir" / "Analizar imagen"); no procesar por defecto todos los medios.
- **Reducción estimada sobre el costo real**: **50–100%** del costo real de medios (referencia: [COSTOS.md](./COSTOS.md), ítem 3 — \$8.95–9.60/médico/mes cuando se transcribe y analiza todo). Si solo se analiza un subconjunto pequeño, el ahorro es muy alto.

### 5.3 Si en el futuro se almacenara en cloud

- **Qué hace**: Si más adelante se decidiera persistir medios en cloud (p. ej. por trazabilidad o normativa), aplicar clase de almacenamiento adecuada (Nearline/Coldline/Archive), lifecycle y compresión para no disparar costos.
- **Cómo**: Lifecycle rules en el bucket; compresión en upload; límite de tamaño y retención según política legal.
- **Referencia**: Estrategias clásicas de ahorro en [Google Cloud Storage pricing](https://cloud.google.com/storage/pricing) (clases, egress).

---

## 6. Speech-to-Text (transcripción de audio)

### 6.1 Usar solo cuando aporte valor

- **Qué hace**: No transcribir todo el audio por defecto; solo cuando se necesite para búsqueda, accesibilidad o análisis.
- **Cómo**: Transcripción bajo demanda (ej. "transcribir" en la UI) o solo para audios que el médico marque como "necesitan transcripción". Por defecto el médico escucha el audio directamente; no se envía a la nube salvo para transcribir.
- **Reducción estimada sobre el costo real**: **50–100%** del costo real de STT (referencia: [COSTOS.md](./COSTOS.md), ítem 3 — \$8.64–9.60/médico/mes si se transcribe todo). Si solo se transcribe un subconjunto (ej. 20% de los audios), ahorro ~80% en ese ítem.

### 6.2 Aprovechar el tier gratuito (Google Speech-to-Text V1)

- **Qué hace**: Las primeras 60 min/mes son gratis en la API V1 (estándar). Para volúmenes bajos, quedarse dentro del gratis.
- **Cómo**: Usar Speech-to-Text V1 para cuentas o proyectos con poco volumen; medir uso mensual.
- **Reducción estimada sobre el costo real**: **~6%** del costo real de STT (60 min gratis sobre 600 min ≈ 10%; en la práctica 60 min × \$0.016 = \$0.96 de ahorro sobre \$9.60). Para volúmenes muy bajos, hasta **100%** si no se superan 60 min/mes.

### 6.3 Caché de transcripciones

- **Qué hace**: No volver a transcribir el mismo audio; guardar transcripción por hash del archivo (ya implementado con `stt_cache_ttl` en params).
- **Cómo**: Mantener TTL largo (ej. 30 días o permanente para historial); clave = hash del audio.
- **Reducción estimada sobre el costo real**: **Variable**; evita 100% del costo de las llamadas repetidas para el mismo archivo (sobre el costo real de STT en [COSTOS.md](./COSTOS.md), ítem 3).

### 6.4 Procesamiento por lotes (batch) si aplica

- **Qué hace**: Speech-to-Text V2 ofrece "Dynamic Batch Recognition" a \$0.003/min (más barato que en tiempo real) con ventana de 24 h.
- **Cómo**: Para transcripciones no urgentes, enviar a batch en lugar de solicitud síncrona.
- **Reducción estimada sobre el costo real**: **~81%** del costo de STT por minuto (de \$0.016 a \$0.003; referencia: [COSTOS.md](./COSTOS.md), ítem 3) a cambio de latencia.

---

## 7. Vision API (análisis de imágenes)

### 7.1 Aprovechar el tier gratuito

- **Qué hace**: Las primeras 1.000 unidades/mes son gratis; hasta 1.000 imágenes (una feature por imagen) no generan costo.
- **Cómo**: Mantener uso por debajo de 1.000 cuando sea posible; concentrar análisis en los casos que realmente lo requieran.
- **Reducción estimada sobre el costo real**: En el escenario de [COSTOS.md](./COSTOS.md) (1.200 imágenes → \$0.30), **~100%** si se baja a ≤1.000 imágenes; si no, la parte gratuita ya reduce el costo real de Vision (ítem 3).

### 7.2 Analizar solo cuando aporte valor

- **Qué hace**: No ejecutar Vision en todas las fotos; solo cuando se necesite detección de texto, etiquetas o otra feature para búsqueda, moderación o asistencia al médico.
- **Cómo**: Feature flag o flujo explícito ("analizar imagen"); un solo feature por imagen cuando baste.
- **Reducción estimada sobre el costo real**: **50–100%** del costo real de Vision (referencia: [COSTOS.md](./COSTOS.md), ítem 3 — \$0.30/médico/mes en uso máximo) si se desactiva por defecto o se limita a un subconjunto.

### 7.3 Una sola feature por imagen

- **Qué hace**: Cada feature (Label, Face, Text, etc.) se factura por separado; usar solo la necesaria.
- **Cómo**: No invocar Face + Label + Text si con uno basta (ej. solo Text para OCR).
- **Reducción estimada sobre el costo real**: **50–66%** del costo de Vision (referencia: [COSTOS.md](./COSTOS.md), ítem 3) si se pasa de 2–3 features a 1 por imagen.

---

## 8. Videollamadas

### 8.1 Proveedor y tipo de plan

- **Qué hace**: Comparar precios por minuto (Twilio, etc.) frente a planes por asiento o por institución (Daily.co, Jitsi self-hosted).
- **Cómo**: Si hay muchos médicos, un plan por asiento con techo de minutos suele ser más barato que puro pay-per-minute. Evaluar Jitsi (open source) si hay capacidad de operar servidores.
- **Reducción estimada sobre el costo real**: **20–50%** del costo real de videollamadas (referencia: [COSTOS.md](./COSTOS.md), ítem 4 — \$10–17.30/médico/mes). Ejemplo: \$17.30 → \$10 con plan por asiento para 10 médicos ≈ 42%.

### 8.2 Límites razonables de duración

- **Qué hace**: Evitar videollamadas muy largas por defecto (ej. aviso a los 15–20 min o corte suave) para no disparar minutos sin control.
- **Cómo**: Límite configurable por tipo de consulta; notificación antes del corte; opción de extender si el médico lo autoriza.
- **Reducción estimada sobre el costo real**: **10–25%** del costo real de videollamadas ([COSTOS.md](./COSTOS.md), ítem 4) si la duración media baja (ej. de 12 a 10 min).

### 8.3 Calidad adaptable y solo audio como fallback

- **Qué hace**: Reducir resolución/bitrate en conexiones malas; ofrecer "solo audio" para ahorrar ancho de banda y a veces costos del proveedor.
- **Cómo**: Configuración WebRTC o del SDK del proveedor (downgrade a audio-only en caso de fallo de video).
- **Reducción estimada sobre el costo real**: **Variable**; menor consumo de bandwidth; impacto en % del costo real de video ([COSTOS.md](./COSTOS.md), ítem 4) depende del modelo de precios del proveedor.

---

## 9. Monitoreo y gobernanza

### 9.1 Métricas de uso y costo

- **Qué hace**: Medir llamadas a IA, minutos de STT, imágenes enviadas a Vision, GB almacenados, minutos de video, por médico o por institución, para detectar desvíos.
- **Cómo**: Logs y métricas (por request o por día); dashboards por servicio; alertas si se superan umbrales.
- **Beneficio**: Permite aplicar las estrategias anteriores con datos (qué reducir primero, dónde hay picos).

### 9.2 Cuotas y límites por usuario o institución

- **Qué hace**: Evitar que un solo usuario o institución genere un costo desproporcionado (ej. uso ilimitado de IA o de video).
- **Cómo**: Límites por plan (ej. X mensajes de IA/mes, Y min de video/mes); mensaje claro al acercarse al límite.
- **Beneficio**: Presupuesto predecible y reparto justo de costos.

### 9.3 Revisión periódica de precios de proveedores

- **Qué hace**: Los precios de GCP, AWS, Twilio, etc. cambian; revisar cada 6–12 meses y comparar con alternativas.
- **Cómo**: Calendarizar revisión; usar calculadoras oficiales y cotizaciones; probar otro proveedor en staging si el ahorro es relevante.
- **Beneficio**: Ajustar estrategia (proveedor, plan, región) cuando haya cambios significativos.

---

## 10. Resumen ejecutivo y checklist

### Checklist de reducción de costos

- [ ] **IA**: Caché activado con TTL adecuado; uso condicional (reglas/diccionarios antes de IA); modelo/proveedor más barato donde la calidad lo permita; límite de tokens.
- [ ] **Hosting**: Optimizaciones de código aplicadas; plan de hosting elegido según estabilidad vs. precio; volumen agrupado para economías de escala.
- [ ] **Pre-consulta**: Menos % de mensajes que llaman a IA; respuestas predefinidas y caché; límite de mensajes o ventana temporal.
- [ ] **Onboarding**: Flujos guiados y FAQ primero; caché por tipo de consulta.
- [ ] **Medios**: Sin almacenamiento en cloud (modelo actual); enviar a IA (STT/Vision) solo cuando se necesite analizar.
- [ ] **STT**: Transcribir solo cuando aporte valor; tier gratis 60 min (V1); caché; batch si no urge.
- [ ] **Vision**: Respetar tier gratis 1.000; analizar solo cuando aporte; una feature por imagen cuando baste.
- [ ] **Videollamadas**: Plan por asiento o por institución si conviene; límite de duración; calidad adaptable.
- [ ] **Gobernanza**: Métricas de uso y costo; cuotas por usuario/institución; revisión periódica de precios.

### Orden sugerido de implementación

1. **Rápido y alto impacto**: Caché IA y STT; uso condicional de IA (ya parcialmente en corrección de texto); respuestas predefinidas en pre-consulta y onboarding.
2. **Configuración**: Proveedor/modelo (Flash vs Pro, HuggingFace tier gratis); plan de hosting; lifecycle y clase de almacenamiento.
3. **Producto y políticas**: Límites de mensajes, duración de video, transcripción opcional; una sola feature en Vision cuando baste.
4. **Operación**: Monitoreo, cuotas y revisión periódica de precios.

---

## Referencias

- [COSTOS.md](./COSTOS.md) – Análisis de costos por plan y por capacidades.
- [CAPACIDADES_PACIENTE_MEDICO.md](./CAPACIDADES_PACIENTE_MEDICO.md) – Descripción de las capacidades que generan costos.
- [FLUJO_CORRECCION_TEXTO_MEDICO.md](./FLUJO_CORRECCION_TEXTO_MEDICO.md) – Ejemplo de uso condicional de IA (SymSpell + IA).
- [GOOGLE_CLOUD_SETUP.md](./GOOGLE_CLOUD_SETUP.md) – Configuración de APIs de Google Cloud.
- Parámetros de aplicación: `web/frontend/config/params.php` (cachés, proveedor, modelos).
