# Bioenlace

**Fuente interna:** mapa de madurez (revisión alineada al producto en construcción)

---

## 1. ¿Qué es un HIS?

Un **HIS** (Hospital Information System, en español: **sistema de información hospitalario**) es el conjunto de software y procesos que permite a una institución de salud operar de punta a punta: **agendar**, **atender**, **prescribir**, **pedir estudios**, **recibir resultados**, **internar**, **facturar** y **gestionar stock**, sobre **un mismo registro del paciente**.

En un hospital muy digitalizado, esos circuitos están conectados: lo que ocurre en consulta alimenta laboratorio y farmacia; lo que ocurre en guardia puede derivar en internación; la facturación refleja lo clínico real. Un HIS “completo” es ese **estado de referencia**, no un único proveedor ni un único módulo.

**Bioenlace** hoy es una plataforma fuerte en **consulta ambulatoria**, **agenda y relación con el paciente** (turnos, resúmenes, notificaciones, recetas y laboratorio integrado desde proveedores externos). Aún tiene **brechas importantes** en facturación hospitalaria plena, farmacia con stock, logística y quirófano avanzado.

---

## 2. Cómo leer las cifras

Cada área del hospital se califica de **0 a 4**:

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
| **Completitud media orientativa** | **~66 %** |
| Áreas ≥ 75 % (nivel 3) | 8 de 12 |
| Áreas ≤ 50 % (nivel ≤ 2) | 4 de 12 |

### Dónde Bioenlace ya compite con fuerza

- **Acceso y demanda:** agenda por institución y profesional, autogestión del paciente, reprogramación y notificaciones.
- **Consulta ambulatoria:** registro de la atención, captura asistida (texto/voz), pedidos, receta emitida y resumen claro para el paciente tras la consulta.
- **Engagement del paciente:** resumen post-consulta automático, consulta de recetas y laboratorio, conversación guiada para acciones frecuentes.
- **Urgencias / guardia:** triage (incl. flujo UI en asistente), tablero en inicio (web y móvil), pedidos/lab, cama, SLA, asignación y egreso auditable.
- **Gestión de demanda:** KPIs de agenda (no-show, días hasta la cita) y adherencia a planes de tratamiento para el equipo.
- **Cumplimiento orientado a staff:** expediente amplio bajo demanda (generación en segundo plano, sin exposición al paciente).

Eso define un **wedge** claro: instituciones que quieren **mejor experiencia ambulatoria y captación/retención de pacientes**, no aún un HIS monolítico de facturación y logística.

### Dónde está el mayor gap de mercado (y de inversión)

- **Facturación y cobranza** integradas al acto médico en todos los puntos de atención.
- **Farmacia hospitalaria** (stock, dispensación, validación) y cierre con receta nacional homologada.
- **Quirófano y materiales** (trazabilidad, tablero de salas, insumos).
- **Regulatorio y cobranza:** receta nacional homologada y obras sociales en reserva/atención.
- **Internación (refinamiento):** firma digital del alta; flujo único quirófano–internación–facturación.


### Lectura para inversión

| Dimensión | Lectura breve |
|-----------|----------------|
| **Producto actual vendible** | Consulta + agenda + paciente digital + receta + lab externo |
| **Expansión AR/LatAm** | Receta nacional y obras sociales en agenda/facturación son palancas regulatorias y de monetización |

---

## 4. Mapa por área

| Área | Nivel (0–4) | % | Mensaje en una línea |
|------|-------------|---|----------------------|
| Quirófanos | 2 | 50 % | Cirugía y agenda básica; falta quirófano “enterprise” |
| Urgencias / guardia | 4 | 95 % | Triage, tablero, pedidos/lab, cama, SLA y CSV |
| Internación | 3,3 | 82 % | Mapa web/móvil, alta con plantillas, ABM plantillas por efector |
| Laboratorio | 2,5 | 63 % | Trae resultados de labs externos; no es lab propio |
| Farmacia | 1,5 | 38 % | Prescripción y receta; sin dispensación ni stock |
| Receta electrónica | 3 | 75 % | Emisión y PDF paciente; falta homologación nacional plena |
| Servicios y especialidades | 3 | 75 % | Catálogo, profesional por institución, turnos y consultas |
| Materiales y logística | 1,5 | 38 % | Consumos parciales; sin depósito ni compras |
| Facturación y contabilidad | 1,5 | 38 % | Bases de nomenclador; sin ciclo factura–cobro pleno |
| Atención ambulatoria | 3 | 75 % | Núcleo clínico actual + resumen paciente + expediente staff |
| Agenda y turnos | 3,25 | 81 % | Reserva, conflicto, notificaciones y KPIs no-show / lead time |
| Planes de tratamiento | 3 | 75 % | Planes activos, recordatorios paciente y dashboard adherencia staff |

---

## 5. Detalle por área

### 5.1 Quirófanos (50 %)

**Qué es:** planificación y ejecución de cirugías, salas, equipos y documentación quirúrgica.

**Lo que Bioenlace cubre hoy**

- Registro de cirugías y agenda quirúrgica en uso.
- Vínculo parcial con internación y prácticas.
- Informe clínico de la atención unificado (no solo un texto suelto en la ficha de cirugía).

**Lo que falta**

- Lista de espera electiva, priorización y preoperatorio estructurado.
- Partes anestésico y quirúrgico formales y checklist de seguridad (OMS).
- Trazabilidad de insumos e implantes en pabellón.
- Tablero de ocupación de salas en tiempo real.
- Integración fuerte con facturación y stock.

**Implicación de producto:** oportunidad de módulo premium o partnership; no es el motor de ingresos actual.

---

### 5.2 Urgencias y guardia (95 %)

**Qué es:** atención de urgencias, registro del episodio, priorización, cola operativa y derivación a internación o consulta.

**Lo que Bioenlace cubre hoy**

- Registro de episodios de guardia por paciente e institución (libro e ingresos).
- **Triage** Manchester (1–5), motivo, signos vitales opcionales y **re-triage** con evento auditable.
- **Tablero operativo** en inicio web y app médico (cola, estado del circuito, minutos de espera, indicadores resumen).
- **Circuito:** tomar caso, iniciar atención con captura clínica, derivar a otro efector, egreso alineado al libro.
- **Indicadores** door-to-triage y door-to-doctor (día actual + materialización nocturna opcional).
- Notificaciones push (servidor + FCM app médico); intents de asistente para tablero y triage con **UI JSON** (lista sin triage + formulario).
- **Pedidos y laboratorio** en el tablero (resumen clínico, alta rápida de pedidos, informes por encounter).
- **Internación:** solicitud de cama, badge pendiente e ingreso web con vínculo `id_guardia`.
- **SLA** configurable por efector (`efector_emergency_config`) con alerta visual en tablero.
- **Export CSV** de indicadores para dirección médica.

**Lo que falta (refinamiento)**

- Catálogo de estudios / envío directo al LIS (hoy la indicación queda en Bioenlace).
- UI de administración para umbrales SLA y alerta sonora en sala.
- Integración FHIR de guardia al mismo nivel que ambulatorio en una sola pantalla longitudinal.

**Implicación de producto:** guardia vendible como módulo operativo completo en hospitales medianos; el siguiente salto es **interoperabilidad LIS nativa** y **mapa de camas en tiempo real**.

---

### 5.3 Internación (82 %)

**Qué es:** paciente internado, cama, evolución, prácticas y consumos del episodio.

**Lo que Bioenlace cubre hoy**

- Episodios de internación con pisos y camas.
- Prácticas, consumos y medicación ligados al episodio.
- Vínculo parcial con nomencladores y facturación según la institución.
- Ingreso desde guardia con trazabilidad `id_guardia`.
- **Mapa de camas** en web y app médico: libre, ocupada, bloqueada, aislamiento; cambio de estado desde web.
- **Indicadores** de ocupación y estadía (media/mediana) en API y cabecera web.
- **Alta estructurada** con epicrisis, checklist, responsable de sesión y vista previa de plantilla.
- **Plantillas de epicrisis** por efector/servicio (y globales de sistema) con placeholders clínicos.
- **ABM administrativo** de plantillas en web y API (alta, edición, activar/desactivar).
- Flujos de asistente para mapa de camas y alta estructurada (UI JSON).

**Lo que falta**

- Firma digital del responsable del alta.
- Integración quirófano–internación–facturación en un solo flujo.
- Administración de plantillas en app móvil (hoy web + API).

---

### 5.4 Laboratorio (63 %)

**Qué es:** en un hospital con laboratorio propio, todo el circuito de pedido, muestra, análisis y entrega de resultados. En Bioenlace el enfoque actual es **integrar laboratorios ya existentes** (terceros) y mostrar resultados dentro de la plataforma.

**Lo que Bioenlace cubre hoy**

- Obtención periódica de informes desde laboratorios externos.
- Almacenamiento y consulta por paciente y por consulta.
- Sincronización programada (lotes o por paciente).
- Informe en PDF para el paciente.
- Listado y detalle dentro de Bioenlace, también vía conversación guiada.
- Enlace del informe a la consulta donde se pidió el estudio, cuando corresponde.
- Estado del pedido (pendiente / con resultado) en el resumen de atención al paciente.

**Lo que falta**

- Pedido de laboratorio de punta a punta **dentro** de la institución (muestra en planta, validación por bioquímico).
- Flujo por sectores del laboratorio hospitalario.
- Catálogo de estudios y rangos gestionado en planta.
- Conectores masivos “plug and play” con cualquier laboratorio sin proyecto a medida.
- Aviso automático al paciente al liberar resultado (hoy el paciente consulta cuando quiere).

**Implicación de producto:** modelo asset-light (integración) vs construir LIS propio (capex de producto alto).

---

### 5.5 Farmacia (38 %)

**Qué es:** validación, preparación y entrega de medicamentos, stock y vínculo con receta.

**Lo que Bioenlace cubre hoy**

- Indicación y prescripción en la consulta o internación.
- Receta electrónica emitida con documento para el paciente (ver receta electrónica).
- Medicamentos codificados en parte de los flujos.

**Lo que falta**

- Dispensación en farmacia hospitalaria con stock y estado “entregado”.
- Validación farmacéutica central y alertas de interacciones.
- Trazabilidad de lote y cadena de frío.
- Cierre con farmacia comunitaria y receta digital nacional.

---

### 5.6 Receta electrónica (75 %)

**Qué es:** documento legal de prescripción, emitido por el profesional y consultable por el paciente y terceros según normativa.

**Lo que Bioenlace cubre hoy**

- Documento de receta separado de la mera indicación en consulta.
- Borrador, emisión y anulación desde la atención.
- Numeración, vigencia, código de verificación e integridad del documento.
- PDF generado en servidor y descarga para el paciente.
- Consulta en Bioenlace, incluido flujos conversacionales.
- Acceso desde el resumen de atención publicado al paciente.

**Lo que falta**

- Firma y validez plena según normativa nacional argentina (repositorio oficial, PKI).
- Estado de receta en farmacia (dispensada, rechazada).
- Auditoría exportable para regulador.

**Implicación de producto:** monetizable hoy en instituciones; escalón regulatorio nacional es siguiente hito de mercado.

---

### 5.7 Servicios y especialidades (75 %)

**Qué es:** qué se ofrece en cada institución (cardiología, laboratorio, etc.) y qué profesional atiende en cada servicio.

**Lo que Bioenlace cubre hoy**

- Catálogo de servicios por institución (efector).
- Profesional asignado a institución y servicio para agenda y consulta.
- Turnos y consultas ambulatorias ligados al servicio.
- Contexto de trabajo del staff (institución, servicio, tipo de atención).
- Motivos de consulta y captura alineados al turno.

**Lo que falta**

- Reglas de cobertura de obras sociales y prepagas en todos los flujos.
- Capacidad física (consultorios, equipos) como restricción de agenda.
- Reportes de producción por servicio para dirección médica.

---

### 5.8 Materiales y logística (38 %)

**Qué es:** depósito, stock, compras, trazabilidad de insumos e implantes.

**Lo que Bioenlace cubre hoy**

- Registro de consumos en internación y parte de consultas.
- Nomencladores de prácticas y suministros en configuraciones existentes.

**Lo que falta**

- Stock en tiempo real y depósito.
- Pedidos internos, recepción y lote.
- Integración con quirófano y farmacia.
- Compras y proveedores.

---

### 5.9 Facturación y contabilidad (38 %)

**Qué es:** facturar el acto médico, cobrar, liquidar con financiadores y contabilidad hospitalaria.

**Lo que Bioenlace cubre hoy**

- Prácticas y consumos que alimentan facturación en algunos recorridos.
- Nomencladores (por ejemplo SUMAR) según configuración de la institución.

**Lo que falta**

- Ciclo completo factura–cobro–contabilidad.
- Validación online con obras sociales en reserva y atención.
- Liquidación, conciliación y reportes financieros.
- Costo por episodio unificado para gestión.

**Implicación de producto:** clave para contratos hospitalarios grandes; esfuerzo largo y sensible a integraciones locales.

---

### 5.10 Atención ambulatoria (75 %)

**Qué es:** la consulta en consultorio (o equivalente ambulatorio): registro, evolución, pedidos, recetas y cierre de la atención.

**Lo que Bioenlace cubre hoy**

- Registro de consultas ambulatorias con ciclo de vida (incluido cierre de la atención).
- Captura por texto o voz, asistencia para estructurar y guardar la evolución (incluye texto claro para el paciente al cerrar).
- Diagnósticos y problemas activos, pedidos de estudios, medicación y receta vinculados a la misma consulta.
- Resultados de laboratorio visibles en el contexto de la atención.
- **Resumen para el paciente:** unos minutos después de finalizar la consulta, publicación automática, notificación y vista con enlaces a receta, laboratorio y pedidos.
- **Expediente amplio para el equipo:** el staff autorizado puede solicitar un PDF completo generado en segundo plano; el paciente no lo descarga desde la app de paciente.

**Lo que falta**

- Historia clínica longitudinal única en pantalla para el médico (sin depender de exportar PDF).
- Misma profundidad de modelo en internación y guardia que en ambulatorio.
- Derivaciones estructuradas (nueva consulta, turno futuro) como producto explícito.
- Intercambio estándar con otras redes de salud (mensajería clínica entre sistemas).

**Implicación de producto:** este es el **core** actual; buena historia para retención de pacientes y diferenciación frente a agenda suelta.

---

### 5.11 Agenda y turnos (81 %)

**Qué es:** reserva de citas, políticas de cancelación, conflictos de agenda y recordatorios.

**Lo que Bioenlace cubre hoy**

- Agenda por profesional, institución y servicio con cupos.
- Paciente reserva, cancela y reprograma según reglas de la institución.
- Turnos “en resolución” cuando cambia la disponibilidad del profesional.
- Sobreturnos y cancelación masiva de un día para el staff.
- Notificaciones al paciente (recordatorios, cambios, resumen listo, etc.).
- Alta de turno por staff para un tercero.
- Flujos guiados por conversación para turnos.
- **Indicadores de agenda (staff):** no-show, tasa y mediana de días entre alta del turno y fecha de la cita, con filtro por período y PES.

**Lo que falta**

- Lista de espera entre instituciones con prioridad clínica.
- Autorización de obra social en el mismo flujo de reserva.
- Teleconsulta como modalidad nativa en agenda donde aplique.
- Export histórico (CSV/PDF) y comparación entre servicios del efector.

**Implicación de producto:** motor de adquisición y uso; las métricas ya permiten demostrar ROI (ausentismo y plazos de acceso).

---

### 5.12 Planes de tratamiento (75 %)

**Qué es:** plan de seguimiento post consulta o crónico (medicación, controles, hábitos) con seguimiento del paciente.

**Lo que Bioenlace cubre hoy**

- Plan vinculado al paciente y opcionalmente a una consulta.
- Actividades con estados (pendiente, hecho, etc.).
- Vista de planes activos para el paciente.
- Recordatorios en el dispositivo del paciente, con preferencias por actividad cuando aplica.
- Pantalla de detalle del plan.
- **Dashboard staff de adherencia** por efector: resumen global y lista de planes con % de actividades completadas.

**Lo que falta**

- Adherencia vinculada a outcomes clínicos (no solo tareas marcadas).
- Integración automática con laboratorio de controles o farmacia.
- Sugerencias asistidas por IA con aprobación médica explícita.
- Versionado y auditoría regulatoria del plan.

**Implicación de producto:** refuerzo de retención y chronic care; el equipo ya puede priorizar seguimiento por baja adherencia.

---

## 6. Priorización sugerida (lente producto / inversión)

Orden orientativo de **retorno vs esfuerzo**, no compromiso de roadmap (actualizado mayo 2026):

| Prioridad | Iniciativa | Por qué ahora |
|-----------|------------|----------------|
| **1** | **Receta nacional + obras sociales en agenda** | Desbloquea mercado AR/LatAm y ticket; gap regulatorio explícito. |
| **2** | **Historia clínica longitudinal (médico)** | El core ambulatorio ya es fuerte; falta una sola vista sin exportar PDF. |
| **3** | **Guardia refinamiento** | SLA por UI admin, aviso sonoro, catálogo LIS en pedidos de guardia. |
| **4** | **Adherencia → outcomes** | Extender el dashboard staff con labs de control y dispensación cuando existan. |
| **5** | **Internación — firma y facturación** | Cerrar alta con validez legal e integración financiera del episodio. |
| **6** | **Facturación integrada** | Enterprise revenue; integraciones pesadas por institución. |
| **7** | **Farmacia + stock / quirófano + logística** | Módulos largos; vender por proyecto cuando el cliente lo exija. |

**Hecho recientemente (no repetir como prioridad inmediata):** guardia post-v1 (pedidos, cama, SLA, CSV); triage UI JSON en asistente; KPIs agenda (`indicadores-agenda`); dashboard adherencia planes staff; internación operativa (mapa, indicadores, alta estructurada, ABM plantillas epicrisis, mapa móvil IMP).
