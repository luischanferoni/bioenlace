# Bioenlace

**Fuente interna:** mapa de madurez [his-completo/](./README.md)

**Última revisión del informe:** 2026-08-18 — alineado a producto (Solicitar Atención, teleconsulta y mensaje, journey pre/post, agentes de agenda y laboratorio, captura unificada).

---

## 1. ¿Qué es un HIS?

Un **HIS** (sistema de información hospitalario) es el conjunto de software y procesos que permite a una institución de salud operar de punta a punta: **agendar**, **atender**, **prescribir**, **pedir estudios**, **recibir resultados**, **internar**, **facturar** y **gestionar stock**, sobre **un mismo registro del paciente**.

En un hospital muy digitalizado esos circuitos están conectados. Un HIS “completo” es ese **estado de referencia**, no un único proveedor ni un único módulo.

**Bioenlace** hoy es una plataforma fuerte en **atención ambulatoria**, **agenda y relación con el paciente**, **guardia operativa** e **internación de piso**. El núcleo clínico es un **registro unificado de cada atención** (ambulatorio, guardia e internación). Aún hay **brechas importantes** en facturación hospitalaria plena, farmacia con stock, logística y quirófano avanzado.

---

## 2. Cómo leer las cifras

Cada área se califica de **0 a 4**:

| Nivel | Significado en lenguaje de negocio |
|-------|-----------------------------------|
| **0** | No existe en el producto |
| **1** | Prueba o piloto muy limitado |
| **2** | Operación básica posible, con trabajo manual fuera del sistema |
| **3** | Cubre el día a día de muchas instituciones; faltan piezas “enterprise” |
| **4** | Nivel de hospital de referencia muy maduro en ese dominio |

---

## 3. Resumen

### Posición global

| Indicador | Valor |
|-----------|--------|
| Áreas evaluadas | 12 |
| **Completitud media orientativa** | **~69 %** |
| Áreas ≥ 75 % (nivel 3) | 7 de 12 |
| Áreas ≤ 50 % (nivel ≤ 2) | 4 de 12 |

### Dónde Bioenlace ya compite con fuerza

- **Acceso y demanda:** agenda por institución y profesional, autogestión del paciente (incl. familia), triage de seguridad, teleconsulta cuando aplica, reprogramación y avisos. Si se libera un cupo, se puede ofrecer adelantar; si el paciente no responde, se escala el aviso.
- **Atención ambulatoria:** una sola puerta (“qué necesitás”), captura asistida (texto/voz), diagnósticos, pedidos, receta emitida y resumen claro al cerrar. Preparación del encuentro (motivos, cuestionarios) visible para el equipo.
- **Canales del paciente:** app, asistente y WhatsApp (el paciente escribe primero); consulta por mensaje a un profesional real; recetas y laboratorio en el teléfono.
- **Urgencias / guardia:** triage, tablero en inicio (web y móvil), captura, internación o derivación, SLA e indicadores.
- **Internación:** mapa de camas, alta en la misma captura del médico, plantillas de epicrisis, seguimiento después del alta.
- **Gestión de demanda:** KPIs de agenda (ausentismo, plazos) y adherencia a planes para el equipo.

Eso define un **wedge** claro: instituciones que quieren **mejor operación ambulatoria, agenda, captura y guardia**, no aún un HIS monolítico de facturación y logística. El [modelo de ingreso comercial](../modelo-de-negocio/business-plan/README.md) se apoya en licencia institucional y vías al financiador — no en cobrar al efector por captar pacientes.

### Dónde está el mayor gap (y de inversión)

- **Facturación y cobranza** integradas al acto en todos los puntos de atención.
- **Farmacia hospitalaria** (stock, dispensación) y cierre con receta nacional homologada.
- **Quirófano y materiales** (trazabilidad, tablero de salas, insumos).
- **Obras sociales** en reserva y atención.

### Lectura para inversión

| Dimensión | Lectura breve |
|-----------|----------------|
| **Producto actual vendible** | Ambulatorio + agenda + paciente digital + receta + lab externo + guardia + internación de piso |
| **Expansión AR/LatAm** | Receta nacional y obras sociales en agenda/facturación son palancas regulatorias y de monetización |

---

## 4. Mapa por área

| Área | Nivel (0–4) | % | Mensaje en una línea |
|------|-------------|---|----------------------|
| Quirófanos | 2 | 50 % | Cirugía y agenda básica; falta quirófano “enterprise” |
| Urgencias / guardia | 4 | 95 % | Triage, tablero, captura, cama, SLA |
| Internación | 3,4 | 85 % | Mapa, alta en captura, sugerencia de cama, post-alta |
| Laboratorio | 2,7 | 68 % | Resultados de labs externos + aviso al paciente; no es lab propio |
| Farmacia | 1,5 | 38 % | Prescripción y receta; sin dispensación ni stock |
| Receta electrónica | 3 | 75 % | Emisión y PDF; falta homologación nacional |
| Servicios de salud (oferta del centro) | 3,2 | 80 % | Catálogo institucional, PES, acto ≠ fila de servicios |
| Materiales y logística | 1,5 | 38 % | Consumos parciales; sin depósito ni compras |
| Facturación y contabilidad | 1,5 | 38 % | Bases de nomenclador; sin ciclo factura–cobro |
| Atención ambulatoria | 3,4 | 85 % | Puerta digital + captura + resumen + mensaje/video |
| Agenda y turnos | 3,5 | 88 % | Reserva, teleconsulta, conflictos y agentes de cupo |
| Planes de tratamiento | 3,3 | 83 % | Planes, hub de seguimiento, adherencia staff |

---

## 5. Detalle por área

### 5.1 Quirófanos (50 %)

**Qué es:** planificación y ejecución de cirugías, salas, equipos y documentación quirúrgica.

**Hoy:** registro y agenda básica; vínculo parcial con internación; informe clínico unificado.

**Falta:** lista de espera electiva, partes anestésico/quirúrgico y checklist OMS, trazabilidad de insumos, tablero de salas, facturación y stock del pabellón.

**Implicación:** módulo premium o partnership; no es el motor de ingresos actual.

---

### 5.2 Urgencias y guardia (95 %)

**Qué es:** priorización, cola operativa, atención y derivación a internación o alta.

**Hoy:** triage Manchester, tablero en inicio (web y móvil), captura clínica (alta / internación / derivación en el mismo registro), pedidos y laboratorio del episodio, cama pendiente, SLA e indicadores, historia de episodio.

**Falta (refinamiento):** umbrales SLA en pantalla de administración, aviso sonoro, catálogo de estudios hacia el laboratorio de planta, box/enfermero en el tablero.

**Implicación:** módulo operativo vendible en hospitales medianos. El mapa de camas vive en **internación**, no es el siguiente salto de guardia.

---

### 5.3 Internación (85 %)

**Qué es:** paciente internado, cama, evolución, prácticas y consumos del episodio.

**Hoy:** mapa de camas (web y móvil), indicadores de ocupación y estadía, captura en piso, alta indicada por el médico, plantillas de epicrisis, sugerencia de cama al ingresar, seguimiento programado después del alta.

**Falta:** firma digital del alta; flujo único quirófano–internación–facturación; ABM de plantillas en el celular.

---

### 5.4 Laboratorio (68 %)

**Qué es:** en un hospital con lab propio, pedido–muestra–validación–entrega. En Bioenlace el enfoque es **integrar laboratorios existentes** y mostrar resultados en la plataforma.

**Hoy:** ingesta periódica, consulta por paciente y por atención, PDF, estado del pedido en el resumen, **aviso al paciente** al liberar un informe (y al profesional si es crítico), vínculo automático a la atención cuando el match es claro.

**Falta:** circuito de planta, sectores y validación bioquímica, catálogo analítico propio, conectores masivos sin proyecto a medida.

**Implicación:** modelo liviano (integración) vs construir un LIS (capex alto).

---

### 5.5 Farmacia (38 %)

**Hoy:** prescripción en la atención y receta electrónica para el paciente.

**Falta:** dispensación con stock, validación farmacéutica, lote/cadena de frío, cierre con farmacia comunitaria y receta nacional.

---

### 5.6 Receta electrónica (75 %)

**Hoy:** documento legal interno (borrador, emisión, anulación, PDF, verificación pública). No sale si faltan datos clínicos mínimos.

**Falta:** PKI y repositorio oficial, estado en farmacia, auditoría para el regulador.

**Implicación:** usable hoy en la institución; el salto de mercado es la homologación nacional.

---

### 5.7 Servicios de salud — oferta del centro (80 %)

**Qué es:** qué **ofrece** cada institución (clínica, imágenes, etc.) y qué profesional está **asignado** a esa oferta. No es la especialidad del título ni el estudio SNOMED pedido.

**Hoy:** catálogo por efector, asignación profesional–servicio, turnos y atenciones ligados a esa oferta, puente a actos (el paciente pide una ecografía y el sistema muestra qué área del centro la hace), política de teleconsulta por servicio, reserva por medicina clínica con derivación a especialista.

**Falta:** cobertura de obras sociales en todos los flujos, restricción por consultorio/equipo, reportes de producción para dirección.

---

### 5.8 Materiales y logística (38 %)

**Hoy:** consumos en internación y parte de las atenciones.

**Falta:** depósito, stock, lote, compras, integración con quirófano y farmacia.

---

### 5.9 Facturación y contabilidad (38 %)

**Hoy:** prácticas y nomencladores que pueden alimentar facturación según el efector.

**Falta:** ciclo factura–cobro, validación online con obras sociales, liquidación y costo por episodio.

**Implicación:** clave para contratos hospitalarios grandes; esfuerzo largo.

---

### 5.10 Atención ambulatoria (85 %)

**Qué es:** el encuentro en consultorio (o equivalente): registro, pedidos, recetas y cierre sobre un mismo paciente.

**Hoy:**

- Una puerta para el paciente: malestar, estudio, control o urgencia (sin diagnosticar).
- Captura por texto o voz; el equipo ve motivos y cuestionarios **antes** de atender.
- Receta, laboratorio y pedidos en esa misma atención; resumen automático al paciente.
- Videollamada o consulta por mensaje cuando el caso lo permite.
- Historia exportable a redes (FHIR) y expediente amplio solo para el staff.
- Un familiar o tutor puede operar por el paciente cuando hay representación.

**Falta:** una sola vista longitudinal para el médico (sin PDF), atajo explícito “tengo una derivación”, homologación del intercambio nacional.

**Implicación:** este es el **core** actual. Comercial: licencia por profesional con clases de atención y add-ons ([matriz AR](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md)).

---

### 5.11 Agenda y turnos (88 %)

**Hoy:** cupos por profesional e institución; el paciente reserva, cancela y reprograma; si hay alarma grave no saca turno en la app; teleconsulta cuando el servicio lo permite; si el médico cambia la agenda, el sistema ofrece nuevos horarios o reubica con consentimiento; si alguien cancela, se puede ofrecer adelantar; recordatorios y anti-ausentismo por reglas; indicadores de no-show y plazos para el equipo.

**Falta:** lista de espera entre instituciones, autorización de obra social en la reserva, grillas distintas para presencial y video, piloto de red nacional de turnos, export histórico para dirección.

**Implicación:** motor de uso diario; las métricas ya permiten hablar de ausentismo y acceso.

---

### 5.12 Planes de tratamiento (83 %)

**Hoy:** plan con actividades y recordatorios; el paciente renueva o consulta por mensaje desde el tratamiento o la condición; el equipo ve adherencia; tras un cuestionario de seguimiento el sistema puede avisar al profesional o mandar un mensaje educativo; al alta hospitalaria se programa un seguimiento.

**Falta:** ligar adherencia a resultados de laboratorio o farmacia; planes sugeridos por IA con firma médica; plan preventivo que se materialice al aceptar un control de perfil.

**Implicación:** soporte a crónicos. Comercial: add-on de módulo, no precio por adherencia del paciente.

---

## 6. Priorización sugerida (lente producto / inversión)

Orden orientativo de **retorno vs esfuerzo**, no compromiso de roadmap (agosto 2026):

| Prioridad | Iniciativa | Por qué ahora |
|-----------|------------|----------------|
| **1** | **Receta nacional + obras sociales en agenda** | Desbloquea mercado AR/LatAm; gap regulatorio explícito. |
| **2** | **Historia clínica longitudinal (médico)** | El core ambulatorio ya es fuerte; falta una sola vista sin exportar PDF. |
| **3** | **Guardia — refinamiento** | SLA en administración, aviso sonoro, pedidos hacia el lab de planta. |
| **4** | **Adherencia → outcomes** | Extender el dashboard staff con labs de control y dispensación cuando existan. |
| **5** | **Internación — firma y facturación** | Cerrar el alta con validez legal e integración financiera del episodio. |
| **6** | **Facturación integrada** | Revenue enterprise; integraciones pesadas por institución. |
| **7** | **Farmacia + stock / quirófano + logística** | Módulos largos; vender por proyecto cuando el cliente lo exija. |

**Ya no listar como “próximo salto” (está en producto):** teleconsulta y consulta por mensaje; Solicitar Atención; avisos de laboratorio; adelantar turno / reubicación / anti no-show; mapa de camas y seguimiento post-alta; hub de control y protocolos.

**Nota sobre el PDF:** si existe `informe-ejecutivo.pdf` en esta carpeta, regenerarlo desde este Markdown tras cada revisión sustancial.
