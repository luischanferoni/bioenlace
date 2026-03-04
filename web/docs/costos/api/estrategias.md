# Estrategias de reducción de costos – API

Este documento detalla cómo **reducir el costo real** cuando se usan **APIs** (IA generativa, STT, Vision, videollamadas). El costo real de referencia está en [api/costos.md](costos.md). Las reducciones son **porcentajes sobre ese costo real**.

Estas mismas tácticas, al reducir **llamadas a IA**, también reducen la carga cuando la IA corre en nuestra infra; ver [infra/estrategias.md](../infra/estrategias.md).

---

## Resumen por área

| Área | Costo real ref. (api/costos.md) | Reducción estimada | Palancas principales |
|------|---------------------------------|--------------------|-----------------------|
| IA / modelos (chat, corrección, análisis) | Según modelo y volumen | **40–60%** | Caché, uso condicional, modelo más barato |
| Comunicación pre-turno | $8–16/médico/mes | **30–50%** | Menos % que llama IA, respuestas predefinidas, caché |
| Pre-consulta | $7.50–15/médico/mes | **30–50%** | Idem |
| Onboarding | $2–4/médico/mes | **Hasta 60%** | Flujos guiados, FAQ, caché |
| Medios (STT + Vision) | $8.95–9.60/médico/mes | **50–100%** | Transcribir/analizar solo cuando aporte; tier gratis; caché |
| Videollamadas | $10–17.30/médico/mes | **20–50%** | Plan por asiento, límite de duración, proveedor |

---

## 1. IA y modelos (chat, corrección, análisis)

### 1.1 Caché de respuestas y resultados

- **Qué hace**: Evitar llamadas repetidas al modelo para consultas o correcciones muy similares.
- **Dónde aplica**: Chat (pre-turno, pre-consulta, onboarding), corrección de texto, análisis, embeddings, transcripciones (STT).
- **Cómo**: TTL largos (`ia_cache_ttl`, `correccion_cache_ttl`, `embedding_cache_ttl`, `stt_cache_ttl` en `params.php`). Clave de caché por hash del input (y opcionalmente usuario/contexto).
- **Reducción estimada**: **40–60%** del costo de IA (ref. [api/costos.md](costos.md)).

### 1.2 Uso condicional de IA (no llamar siempre)

- **Qué hace**: Usar reglas, diccionarios o flujos predefinidos primero; llamar al modelo solo cuando sea necesario.
- **Dónde aplica**: Corrección de texto (SymSpell + diccionario + IA condicional), clasificación de intents (reglas/keywords primero, IA como fallback), FAQ.
- **Cómo**: Mantener flujo híbrido; extender a más intents con reglas claras antes de invocar IA.
- **Reducción estimada**: **30–50%** del costo de IA (ref. [api/costos.md](costos.md)).

### 1.3 Elección de proveedor y modelo

- **Qué hace**: Usar el proveedor y modelo más baratos que cumplan calidad mínima.
- **Opciones**: `ia_proveedor` en params: `google` (Vertex/Gemini), `huggingface`, `groq`, `openai`, `ollama`. Gemini Flash más barato que Pro; HuggingFace tier gratuito (30K requests/mes).
- **Reducción estimada**: **20–50%** del costo de IA según cambio de modelo/proveedor (Pro → Flash, tier gratis).

### 1.4 Limitar tokens y complejidad

- **Qué hace**: Reducir `max_tokens` / `maxOutputTokens` y longitudes de prompt.
- **Dónde**: `vertex_ai_max_tokens`, `google_max_output_tokens`, `hf_max_length` en params; prompts acotados en código.
- **Reducción estimada**: **10–30%** del costo de IA por llamada.

### 1.5 Comprimir datos en tránsito

- **Qué hace**: Enviar menos bytes al proveedor (gzip) donde el API lo acepte.
- **Impacto**: Variable; no suele ser el mayor % del ahorro.

---

## 2. Comunicación previa al turno (pre-turno)

El chat que guía al paciente **antes** de sacar el turno (puede terminar en turno o no). Coste de referencia en [api/costos.md](costos.md).

### 2.1 Reducir la fracción de mensajes que llaman a IA

- **Qué hace**: Responder con respuestas predefinidas o flujos guiados (cómo sacar turno, horarios, requisitos); usar IA solo para preguntas abiertas o no catalogadas.
- **Cómo**: Keywords/patrones e intents para “pre-turno”; respuestas tipo plantilla para los más comunes; clasificador por reglas primero.
- **Reducción estimada**: **30–40%** del costo real de pre-turno (ej. bajar de 40% a 25% de mensajes con IA).

### 2.2 Caché por pregunta o intención

- **Qué hace**: Cachear respuestas de IA por hash de pregunta (o intent + parámetros) para que preguntas repetidas no vuelvan a llamar al modelo.
- **Reducción estimada**: **20–40%** adicional si hay muchas preguntas repetidas entre usuarios.

### 2.3 Límite de mensajes o ventana

- **Qué hace**: Acotar cuántos mensajes o en qué ventana se ofrece el chat pre-turno; derivar a FAQ o contacto humano después.
- **Reducción estimada**: **Variable**; reduce el costo de forma directa al bajar el número de mensajes.

---

## 3. Conversación pre-consulta

- **Reducir % que llama a IA**: Respuestas predefinidas y flujos guiados (preparación, documentación, horarios, estado del turno); IA solo para preguntas no catalogadas. **30–40%** del costo real de pre-consulta.
- **Caché por pregunta/intención**: **20–40%** adicional.
- **Límite de mensajes o ventana** (ej. solo 48 h antes del turno): **Variable**.

---

## 4. Agente de onboarding y día a día

- **Flujos guiados y FAQ primero**: Resolver con árboles de decisión, botones y respuestas fijas; IA solo cuando el usuario hace pregunta libre no cubierta. **Hasta 60%** del costo real del agente.
- **Caché por tipo de consulta**: **20–40%**.
- **Reducir interacciones por usuario** (mejor UX, menos pasos): **Variable**.

---

## 5. Medios (STT + Vision)

- **No almacenar en cloud** (modelo actual): Elimina costo de almacenamiento y egress; costo = solo STT/Vision cuando se invoque.
- **Enviar a IA solo cuando aporte valor**: Transcripción y análisis bajo demanda (ej. botón “Transcribir” / “Analizar imagen”). **50–100%** del costo real de medios.
- **STT**: Transcribir solo cuando aporte; tier gratis 60 min (V1); caché; batch si no urge. **50–100%** según uso.
- **Vision**: Tier gratis 1.000 unidades; analizar solo cuando aporte; una feature por imagen cuando baste. **50–100%** según uso.

---

## 6. Videollamadas

- **Proveedor y tipo de plan**: Plan por asiento o por institución (Daily.co, etc.) frente a pago por minuto. **20–50%** del costo real.
- **Límites razonables de duración**: Aviso o corte suave a los 15–20 min. **10–25%** si baja la duración media.
- **Calidad adaptable / solo audio como fallback**: **Variable** según proveedor.

---

## 7. Monitoreo y gobernanza

- **Métricas de uso y costo**: Llamadas a IA, minutos STT, imágenes Vision, minutos de video, por médico o institución; alertas si se superan umbrales.
- **Cuotas y límites** por usuario o institución: Evitar uso desproporcionado; presupuesto predecible.
- **Revisión periódica de precios** de proveedores (cada 6–12 meses).

---

## Referencias

- [api/costos.md](costos.md) – Costos reales por capacidad cuando se usa API.
- [infra/estrategias.md](../infra/estrategias.md) – Cómo reducir coste de infra (las mismas tácticas de “menos IA” también bajan carga en nuestra GPU).
- [FLUJO_CORRECCION_TEXTO_MEDICO.md](../../FLUJO_CORRECCION_TEXTO_MEDICO.md) – Ejemplo de uso condicional de IA.
- Parámetros: `web/frontend/config/params.php` (cachés, proveedor, modelos).
