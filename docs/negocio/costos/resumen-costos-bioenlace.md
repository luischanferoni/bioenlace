# Resumen de costos

## Supuestos de actividad

Todo se expresa **por profesional y por mes**, siguiendo el recorrido del paciente en la consulta.


| Ámbito                  | Supuesto                                                                        |
| ----------------------- | ------------------------------------------------------------------------------- |
| Escala de referencia    | **5.000 profesionales**                                                         |
| Consultas atendidas     | **500 por mes** cada uno (25 por día × 20 días; 15 min por atención ≈ 6 h 15 de consulta + ~1 h 45 administrativas). Media jornada: **250/mes** |
| Conversaciones con el paciente    | **10 mensajes** por consulta ambulatoria (alrededor del turno) |
| Motivos de consulta | **Cuestionario con opciones antes del turno** (sin audio) + **1 resumen automático** + insights por consulta |
| Captura del médico      | **Siempre audio** — **~5 minutos** de voz del profesional por consulta + análisis del texto   |
| Análisis de imágenes      | **No se ofrece** por el momento — costo $0 por alcance, no por franquicia |
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

Chat aparte donde el paciente responde **por qué viene** con **preguntas de opción múltiple** (una opción «Otra» acotada por pregunta y un cierre libre de 320 caracteres), hasta **10 minutos** antes del turno. **No** se analiza cada respuesta al instante; al cerrar el plazo se genera **un solo resumen** para el médico. **No hay audio ni transcripción.**

**Paso a paso**

1. El paciente responde el cuestionario; el sistema ya sabe qué contó al **reservar el turno** (triage) y en el **chat del asistente**, y no vuelve a preguntar lo mismo.
2. Llegada la hora límite, el sistema junta el triage, esos mensajes y las respuestas, y añade un **resumen clínico acotado** del paciente (alergias, condiciones, medicación).
3. **Una** pasada automática (`motivos-consulta-batch`) redacta el motivo de consulta que verá el médico.
4. **Otra** pasada (`motivos-consulta-insights`) genera sugerencias orientativas preliminares (hipótesis / prácticas).

**Ejemplo**

- El paciente eligió «malestar nuevo → pecho» al reservar y respondió «desde hoy», «empeoró», «dolor en el pecho» y un cierre de dos líneas → resumen en un párrafo con eso, sin repetir preguntas.

**Costo mensual (5.000 profesionales)**


| Variante                                   | USD       |
| ------------------------------------------ | --------- |
| Motivos (batch + insights, sin STT)        | **1.375** |


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

### 4. Imágenes y videollamada (referencia)

- **Análisis de imágenes:** **no se ofrece** por el momento; costo $0 por alcance (no por franquicia).
- **Videollamada:** add-on con COGS planificado **USD 2,19**/prof/mes @ **40 %** teleconsulta (self-host sala/TURN/Track Egress/storage; STT una vez, mismo que dictado). Detalle: [costos-api §6](costos-api.md#6-videollamadas-pacientemédico), [videollamadas.md](estrategias-reduccion/videollamadas.md). No incluido en los totales de IA de abajo.

---

## Totales mensuales — 5.000 profesionales

Solo costos de **inteligencia artificial y transcripción de voz** (sin videollamada ni impuestos).

### Escenario base


| Concepto                                       | USD       |
| ---------------------------------------------- | --------- |
| Conversación con el paciente                   | 9.625     |
| Motivos de consulta (sin STT, batch + insights) | 1.375     |
| Captura clínica del médico (siempre con audio)      | 10.562,5  |
| **Subtotal**                                        | **21.562,5** |


### Escenario con onboarding


| Concepto                                       | USD       |
| ---------------------------------------------- | --------- |
| Conversación con el paciente                   | 9.625     |
| Motivos de consulta (sin STT, batch + insights) | 1.375     |
| Captura clínica del médico (~5 min + IA)       | 10.562,5  |
| Onboarding (§3)                                | 875       |
| **Total**                                      | **22.437,5** |

> **Infra app:** además del bloque de IA + STT, el catálogo reserva **0,03/prof/mes** (2–3 VPS KVM2 compartidos) → **150/mes** a 5.000 profesionales. El COGS que usa el pricing es **IA + STT + infra ≈ 21.700/mes** (sin videollamada ni impuestos). **No incluye análisis de imágenes: no se ofrece.**


---

## Composición del COGS (qué pesa en el costo)

Dos vistas separadas: **COGS base por atención** (lo que se vende en cada clase) y el **add-on de videollamada** con su propio desglose. En ambas los % son **sobre el total de su propia configuración**. Escenario base de la lista: chat con el presupuesto comercial conservador, motivos **sin audio** (cuestionario pre-turno), dictado con −30 % on-device. Valores: catálogo `PricingPesByEncounterClassCatalog`; fórmula y márgenes en [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md).

### COGS base por atención (sin videollamada)

| Componente | Ambulatorio | Urgencia (EMER) | Internación (IMP) |
|------------|-------------|-----------------|-------------------|
| Chat del paciente (cupo 10 msgs) | 0,0048 · **55,4 %** | — | — |
| Motivos de consulta (IA, sin STT) | 0,0006 · **6,9 %** | 0,0006 · **15,6 %** | 0,0006 · **15,5 %** |
| Captura clínica — IA (extracción + codificación) | 0,0007 · **8,1 %** | 0,0007 · **18,2 %** | 0,0007 · **18,1 %** |
| Dictado — STT del profesional (~5 min) | 0,0025 · **28,9 %** | 0,0025 · **65,1 %** | 0,0025 · **64,5 %** |
| Infra app + BD + backups + monitoreo | 0,00006 · **0,7 %** | 0,000043 · **1,1 %** | 0,000075 · **1,9 %** |
| **Total por atención** | **0,00866** | **0,003843** | **0,003875** |

Infra: **0,03/prof/mes** repartido entre las atenciones de referencia (AMB 500, EMER 700, IMP 400). Base: 2–3 VPS KVM2 compartidos (~USD 90–160/mes total). Sin análisis de imágenes (no se ofrece); el storage de video va en el add-on.

### Add-on videollamada (40 % teleconsulta)

| Componente | USD / atención | % del add-on |
|------------|----------------|--------------|
| Sala + TURN + Track Egress + ops (self-host, autoescalado) | 0,0019 | **42,9 %** |
| Storage (14 d caliente → Deep Archive, 1 copia) | 0,0025 | **57,1 %** |
| Transcripción | 0 | **0 %** (el STT ya está en dictado) |
| **Total add-on** | **0,0044** | **100 %** |

**La videollamada no encarece el chat.** El chat sigue costando 0,0048 con o sin video; su participación baja de **55,4 % a 36,8 %** solo porque el denominador pasa de 0,00866 a 0,01306 (+51 %). Lo mismo aplica a motivos (6,9 % → 4,6 %), captura (8,1 % → 5,4 %), dictado (28,9 % → 19,1 %) e infra (0,7 % → 0,5 %). Para una comparación homogénea, usar la tabla base.

Lectura:

- En **ambulatorio sin video**, el **chat del paciente** es el ítem más pesado (**~55 %**), seguido del **dictado** (**~29 %**); juntos son ~84 %.
- En **urgencia e internación** (sin chat de paciente), el **dictado** es **~65 %** del costo.
- En el **add-on video**, el **storage** pesa más que la sala en vivo (**57 % vs 43 %**): retención y calidad de grabación son la palanca, no el SFU.
- La **IA de captura** (extracción + codificación) es **8–18 %**; la **infra** es marginal (**0,7–1,9 %**).
- Cada punto del STT se mueve con dos palancas: minutos dictados (5 → 4 min baja el STT 20 %) y transcripción en el dispositivo (−30 %), ver [estrategias-reduccion/stt.md](estrategias-reduccion/stt.md). **Motivos ya no aporta minutos de STT.**

> **Por qué no coincide con los totales por capacidad de arriba:** esos totales salen del escenario token a token de [costos-api.md](costos-api.md) (p. ej. chat medido ~0,0038/atención), mientras que esta tabla usa los presupuestos que fija el catálogo comercial para armar el precio de lista (chat AMB **0,0048**/atención, deliberadamente conservador). Misma realidad, dos supuestos: uno mide, el otro presupuesta.

---

## Notas

- Por profesional y mes (500 atenciones, sin videollamada): **~USD 4,31 bruto · ~USD 3,95 con context caching** (incluye `motivos-consulta-insights` y STT médico ~5 min; motivos sin audio). Detalle y comparativa DeepSeek: [costos-api.md](costos-api.md).
- **Identidad (Didit):** costo aparte por verificación, no por profesional. El cupo pasó de 500 sesiones a **USD 10 de crédito/mes**; proyección: [costos-didit.md](costos-didit.md).
- **WhatsApp:** asistente solo si el **paciente inicia** el mensaje. **Meta ya no es $0**: desde el 1/10/2026 se cobran los mensajes **service** después de **1.000 por número por mes**. El COGS vigente del chat **no incluye** ese costo; si se habilita WhatsApp como canal, hay que presupuestarlo. Plantillas utility **no habilitadas**. Ver [costos-whatsapp.md](costos-whatsapp.md).
- No incluye videollamada ni impuestos.
- **Precio de lista al cliente:** no es este COGS. Se aplica margen sobre costo (lista **233 %** ≈ 70 % bruto ≈ **42,9 %** después de IIBB + ganancias, ~42,5 % con IDCB; baja por tramos de atenciones) y add-ons opcionales (dictado / videollamada). Ver [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) e [impuestos-argentina.md](impuestos-argentina.md).

