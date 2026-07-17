# Resumen de costos

## Supuestos de actividad

Todo se expresa **por profesional y por mes**, siguiendo el recorrido del paciente en la consulta.


| Ámbito                  | Supuesto                                                                        |
| ----------------------- | ------------------------------------------------------------------------------- |
| Escala de referencia    | **5.000 profesionales**                                                         |
| Consultas atendidas     | **400 por mes** cada uno (20 días x 20 consultas por día)                       |
| Conversaciones con el paciente    | **5 mensajes** por consulta                                                     |
| Motivos de consulta (solo texto): El paciente cuenta los motivos de la consulta antes de la consulta ya reservada | **1 resumen automático** por consulta
| Motivos de consulta (con la posibilidad de audio)       | **~4 minutos de audio** (voz paciente) por consulta, solo si hay audio en ese chat o pista de videollamada |
| Captura del médico      | **Siempre audio** — **~5 minutos** de voz del profesional por consulta + análisis del texto   |
| Fotos clínicas          | **2 por consulta** — hoy **sin costo** en el presupuesto (dentro de franquicia) |
| Videollamada            | **80 %** de las consultas (planificación agresiva), **12 minutos**, paciente y médico; COGS add-on **3,50** |


---

## Capacidades — qué pasa en cada paso

### 1. Conversación con el paciente

El paciente escribe en el chat de la app: turnos, síntomas, dudas o saludos.

**Paso a paso**

1. **Cada mensaje** pasa por una lectura automática: se corrige la redacción y se entiende si pide una acción («quiero un turno»), si solo conversa («me duele la cabeza») o si busca información («¿qué puedo hacer acá?»).
2. Si pide una **acción concreta**, el sistema reconoce la acción con reglas internas y responde con un **listado de opciones**, un **formulario** o un **flujo guiado** (varios pasos en el mismo chat: elegir profesional, fecha, confirmar, etc.) — **sin** una segunda lectura automática solo para elegir qué acción es.
3. Si **conversa** (síntomas, malestar, charla clínica), **cada mensaje** del paciente recibe lectura + respuesta automática; puede haber **varios ida y vuelta** en la misma consulta (el sistema recuerda solo los **últimos turnos** del hilo, no todo el chat). La respuesta incluye un **resumen clínico acotado** del paciente (alergias, condiciones, medicación) para contextualizar sin inventar datos.
4. Si pide **información general** sobre la app, suele ver un **menú de acciones** disponibles (sin charla clínica).

**Ejemplo**

- *«Kiero un turno con la dra García»* → lectura automática → flujo guiado: elegir servicio, día y horario en el chat.
- *«Me duele la garganta hace tres días»* → lectura + respuesta → *«¿Tenés fiebre?»* (paciente responde) → otra lectura + respuesta; no abre trámites de turnos.

**Costo mensual (5.000 profesionales):** **USD 2.250**

---

### 2. Motivos de consulta (antes de la atención)

Chat aparte donde el paciente deja **por qué viene**, en texto, audio o fotos, hasta **10 minutos** antes del turno. **No** se analiza cada mensaje al instante; al cerrar el plazo se genera **un solo resumen** para el médico.

**Paso a paso**

1. El paciente escribe o graba en el chat de motivos (puede ser solo texto, o incluir audios).
2. Llegada la hora límite, el sistema junta todo el hilo y añade un **resumen clínico acotado** del paciente (alergias, condiciones, medicación).
3. **Una** pasada automática (`motivos-consulta-batch`) redacta el motivo de consulta que verá el médico.
4. **Otra** pasada (`motivos-consulta-insights`) genera sugerencias orientativas preliminares (hipótesis / prácticas).

**Ejemplo**

- Solo texto: *«Dolor de pecho al subir escaleras»* + *«Ya tuve algo parecido el año pasado»* → resumen en un párrafo.
- Con audio: el paciente habla ~4 minutos (notas de voz o pista de videollamada) → primero se transcribe → luego el mismo resumen único.

**Costo mensual (5.000 profesionales)**


| Variante                                   | USD       |
| ------------------------------------------ | --------- |
| Motivos **solo texto** (batch + insights)      | **1.100** |
| Motivos **con audio** (~4 min por consulta)  | **6.850** |


---

### 3. Captura clínica del médico

Durante o después de la consulta el médico **dicta en voz alta** lo que atendió. Siempre hay audio: no se presupuesta captura solo escrita.

**Paso a paso**

1. El médico graba el audio (supuesto: **~5 minutos** de voz por consulta; en teleconsulta puede salir de la pista del profesional).
2. El audio se convierte en texto.
3. **Una** pasada automática estructura signos, diagnósticos, indicaciones según el tipo de consulta (con **contexto clínico acotado** del paciente: alergias, condiciones previas, medicación activa).

**Ejemplo**

- Dictado / consulta: *«Paciente con hipertensión controlada, ajusto enalapril, control en 30 días»* → texto transcrito → campos clínicos rellenados o sugeridos.

**Costo mensual (5.000 profesionales):** **USD 8.450**

---

### 4. Fotos y videollamada (referencia)

- **Fotos:** compartir imágenes clínicas; costo presupuestado **cero** hoy.
- **Videollamada:** add-on con COGS planificado **USD 3,50**/prof/mes (self-host sala/TURN/Track Egress/storage; STT una vez, mismo que dictado). Detalle: [costos-api §6](./costos-api.md#6-videollamadas-pacientemédico), [videollamadas.md](./estrategias-reduccion/videollamadas.md). No incluido en los totales de IA de abajo.

---

## Totales mensuales — 5.000 profesionales

Solo costos de **inteligencia artificial y transcripción de voz** (sin videollamada ni impuestos).

### Escenario base (motivos del paciente solo en texto)


| Concepto                                       | USD       |
| ---------------------------------------------- | --------- |
| Conversación con el paciente                   | 2.250     |
| Motivos de consulta (sin audio, batch + insights) | 1.100     |
| Captura clínica del médico (siempre con audio)      | 8.450     |
| **Subtotal**                                        | **11.800** |


### Escenario intensivo (motivos con audio + onboarding)


| Concepto                                       | USD       |
| ---------------------------------------------- | --------- |
| Conversación con el paciente                   | 2.250     |
| Motivos de consulta (con audio, ~4 min)        | 6.850     |
| Captura clínica del médico (~5 min + IA)       | 8.450     |
| Onboarding (§3)                                | 700       |
| **Total**                                      | **18.250** |


---

## Notas

- Por profesional y mes (escenario intensivo, COGS base sin caché): **~USD 3,65** (incluye `motivos-consulta-insights`, STT médico ~5 min + paciente ~4 min, y contexto clínico en prompts). Detalle y comparativa DeepSeek: [costos-api.md](./costos-api.md).
- **Identidad (Didit):** costo aparte por verificación, no por profesional. Con &lt; 500 altas/mes suele ser **USD 0**; proyección: [costos-didit.md](./costos-didit.md).
- **WhatsApp:** asistente solo si el **paciente inicia** el mensaje (Meta ≈ $0). Plantillas utility **no habilitadas**. IA del chat = misma del §1 / app. Ver [costos-api §7](./costos-api.md#7-whatsapp-cloud-api-paciente).
- No incluye videollamada ni impuestos.
- **Precio de lista al cliente:** no es este COGS. Se aplica margen sobre costo (hoy **233 %** ≈ 70 % bruto) y add-ons opcionales (audio / videollamada). Ver [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) e [impuestos-argentina.md](./impuestos-argentina.md).

