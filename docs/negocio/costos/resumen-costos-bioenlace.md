# Resumen de costos

## Supuestos de actividad

Todo se expresa **por profesional y por mes**, siguiendo el recorrido del paciente en la consulta.


| Ámbito                  | Supuesto                                                                        |
| ----------------------- | ------------------------------------------------------------------------------- |
| Escala de referencia    | **5.000 profesionales**                                                         |
| Consultas atendidas     | **500 por mes** cada uno (25 por día × 20 días; 15 min por atención ≈ 6 h 15 de consulta + ~1 h 45 administrativas). Media jornada: **250/mes** |
| Conversaciones con el paciente    | **10 mensajes** por consulta ambulatoria (alrededor del turno) |
| Motivos de consulta | **Siempre con audio** (~4 min voz paciente) + **1 resumen automático** + insights por consulta |
| Captura del médico      | **Siempre audio** — **~5 minutos** de voz del profesional por consulta + análisis del texto   |
| Fotos clínicas          | **2 por consulta** — hoy **sin costo** en el presupuesto (dentro de franquicia) |
| Videollamada            | **40 %** de las consultas ambulatorias (cuando el add-on está contratado), **12 minutos**, paciente y médico; COGS add-on **2,19**/prof/mes (**0,0044**/atención a 500 atenciones) |


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

**Costo mensual (5.000 profesionales, 500 atenciones/mes):** **USD 9.625**

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
| Motivos **solo texto** (batch + insights)      | **1.375** |
| Motivos **con audio** (~4 min por consulta)  | **8.562,5** |


---

### 3. Captura clínica del médico

Durante o después de la consulta el médico **dicta en voz alta** lo que atendió. Siempre hay audio: no se presupuesta captura solo escrita.

**Paso a paso**

1. El médico graba el audio (supuesto: **~5 minutos** de voz por consulta; en teleconsulta puede salir de la pista del profesional).
2. El audio se convierte en texto.
3. **Una** pasada automática estructura signos, diagnósticos, indicaciones según el tipo de consulta (con **contexto clínico acotado** del paciente: alergias, condiciones previas, medicación activa).

**Ejemplo**

- Dictado / consulta: *«Paciente con hipertensión controlada, ajusto enalapril, control en 30 días»* → texto transcrito → campos clínicos rellenados o sugeridos.

**Costo mensual (5.000 profesionales, 500 atenciones/mes):** **USD 10.562,5**

> Con dictado de **4 min** en lugar de 5: el STT baja **USD 0,0005 por atención** (de 0,0025 a 0,0020), es decir **−USD 0,25/prof/mes** y **−USD 1.250/mes** a 5.000 profesionales en este bloque. En el COGS AMB total implica **−5,4 %**.

---

### 4. Fotos y videollamada (referencia)

- **Fotos:** compartir imágenes clínicas; costo presupuestado **cero** hoy.
- **Videollamada:** add-on con COGS planificado **USD 2,19**/prof/mes @ **40 %** teleconsulta (self-host sala/TURN/Track Egress/storage; STT una vez, mismo que dictado). Detalle: [costos-api §6](costos-api.md#6-videollamadas-pacientemédico), [videollamadas.md](estrategias-reduccion/videollamadas.md). No incluido en los totales de IA de abajo.

---

## Totales mensuales — 5.000 profesionales

Solo costos de **inteligencia artificial y transcripción de voz** (sin videollamada ni impuestos).

### Escenario base (motivos del paciente solo en texto)


| Concepto                                       | USD       |
| ---------------------------------------------- | --------- |
| Conversación con el paciente                   | 9.625     |
| Motivos de consulta (sin audio, batch + insights) | 1.375     |
| Captura clínica del médico (siempre con audio)      | 10.562,5  |
| **Subtotal**                                        | **21.562,5** |


### Escenario intensivo (motivos con audio + onboarding)


| Concepto                                       | USD       |
| ---------------------------------------------- | --------- |
| Conversación con el paciente                   | 9.625     |
| Motivos de consulta (con audio, ~4 min)        | 8.562,5   |
| Captura clínica del médico (~5 min + IA)       | 10.562,5  |
| Onboarding (§3)                                | 875       |
| **Total**                                      | **29.625** |


---

## Composición del COGS (qué pesa en el costo)

**USD por atención y % del COGS total** de esa configuración. Escenario base de la lista: chat con el presupuesto comercial conservador, motivos con context caching y blend por clase (AMB 30 % con audio, EMER 45 %, IMP 50 %), dictado con −30 % on-device. Valores: catálogo `PricingPesByEncounterClassCatalog`; fórmula y márgenes en [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md).

| Componente del COGS | Ambulatorio | Ambulatorio + videollamada | Urgencia (EMER) | Internación (IMP) |
|---------------------|-------------|----------------------------|-----------------|-------------------|
| Chat del paciente (cupo 10 msgs) | 0,0048 · **51,6 %** | 0,0048 · **35,0 %** | — | — |
| Motivos de consulta (IA + STT del paciente) | 0,0014 · **15,1 %** | 0,0014 · **10,2 %** | 0,0018 · **36,7 %** | 0,0019 · **38,0 %** |
| Captura clínica — IA (extracción + codificación) | 0,0006 · **6,5 %** | 0,0006 · **4,4 %** | 0,0006 · **12,2 %** | 0,0006 · **12,0 %** |
| Dictado — STT del profesional (~5 min) | 0,0025 · **26,9 %** | 0,0025 · **18,2 %** | 0,0025 · **51,0 %** | 0,0025 · **50,0 %** |
| Videollamada (add-on, 40 % teleconsulta) | — | 0,0044 · **32,1 %** | — | — |
| **Total por atención** | **0,0093** | **0,0137** | **0,0049** | **0,0050** |

Lectura:

- En **ambulatorio sin video**, el **chat del paciente** es el ítem más pesado (**~52 %**), seguido del **dictado** (**~27 %**); juntos son casi 4 de cada 5 dólares.
- En **urgencia e internación** (sin chat de paciente), el **dictado** pasa a ser la mitad del costo (**~50 %**) y **motivos** el segundo (**~37 %**).
- Con **videollamada** contratada, el video se lleva **~32 %** del COGS ambulatorio y el chat baja a **~35 %**.
- La **IA de captura** (extracción + codificación) es marginal en todas las clases (**4–12 %**).
- Cada punto del STT se mueve con dos palancas: minutos dictados (5 → 4 min baja el STT 20 %) y transcripción en el dispositivo (−30 %), ver [estrategias-reduccion/stt.md](estrategias-reduccion/stt.md).

> **Por qué no coincide con los totales por capacidad de arriba:** esos totales salen del escenario token a token de [costos-api.md](costos-api.md) (p. ej. chat medido ~0,0038/atención), mientras que esta tabla usa los presupuestos que fija el catálogo comercial para armar el precio de lista (chat AMB **0,0048**/atención, deliberadamente conservador). Misma realidad, dos supuestos: uno mide, el otro presupuesta.

---

## Notas

- Por profesional y mes (escenario intensivo, 500 atenciones, sin videollamada): **~USD 5,75 bruto · ~USD 5,37 con caché** (incluye `motivos-consulta-insights`, STT médico ~5 min + paciente ~4 min, y contexto clínico en prompts). Detalle y comparativa DeepSeek: [costos-api.md](costos-api.md).
- **Identidad (Didit):** costo aparte por verificación, no por profesional. Con &lt; 500 altas/mes suele ser **USD 0**; proyección: [costos-didit.md](costos-didit.md).
- **WhatsApp:** asistente solo si el **paciente inicia** el mensaje (Meta ≈ $0). Plantillas utility **no habilitadas**. IA del chat = misma del §1 / app. Ver [costos-api §7](costos-api.md#7-whatsapp-cloud-api-paciente).
- No incluye videollamada ni impuestos.
- **Precio de lista al cliente:** no es este COGS. Se aplica margen sobre costo (lista **233 %** ≈ 70 % bruto ≈ 49 % después IIBB + ganancias; baja por tramos de PES) y add-ons opcionales (audio / videollamada). Ver [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) e [impuestos-argentina.md](impuestos-argentina.md).

