# Design — Perfil persistido de comportamiento en turnos

## Separación de responsabilidades

| Pieza | Responsabilidad |
|-------|-----------------|
| Eventos | Registrar qué ocurrió, cuándo, quién lo originó y con qué calidad |
| Perfil | Materializar métricas factuales por persona, ventana y alcance |
| Política | Interpretar métricas para una acción concreta |
| Agente | Ejecutar la acción autorizada y auditar su resultado |
| API/UI | Explicar hechos y decisiones según permisos |

`persona_agenda_preferencias` conserva elecciones explícitas —días, franjas, modalidad, mismo PES y opt-in—. No almacena tasas, historial ni niveles de riesgo.

## Fuente canónica de eventos

Se amplía `turno_evento_audit` como registro canónico, evitando un segundo stream paralelo.

### Catálogo mínimo

| Evento | Resultado |
|--------|-----------|
| `APPOINTMENT_CREATED` | Turno creado |
| `APPOINTMENT_RESCHEDULED` | Turno movido; referencia anterior y nueva |
| `APPOINTMENT_CANCELLED` | Turno cancelado con actor y motivo |
| `APPOINTMENT_ENTERED_RESOLUTION` | Agenda cambió y requiere resolución |
| `CONFIRMATION_REQUESTED` | Se solicitó confirmación |
| `CONFIRMATION_DELIVERY_CONFIRMED` | ACK autenticado de la app al recibir FCM (`mobile_fcm_ack`) |
| `CONFIRMATION_OPENED` | Tap explícito del push (`mobile_push_tap`); no se infiere desde bandeja |
| `CONFIRMED` | La persona confirmó asistencia |
| `ATTENTION_STARTED` | Comenzó la atención |
| `ATTENDED` | Turno cerrado como atendido |
| `NO_SHOW_RECORDED` | Staff registró inasistencia |
| `NO_SHOW_CORRECTED` | Se rectificó una inasistencia |
| `APPOINTMENT_ADVANCE_OFFERED` | Se ofreció adelantar a un slot liberado |
| `APPOINTMENT_ADVANCE_DELIVERED` | La oferta de adelantamiento se entregó (ACK push) |
| `APPOINTMENT_ADVANCE_OPENED` | El paciente abrió la oferta (tap push) |
| `APPOINTMENT_ADVANCE_ACCEPTED` | Aceptó y se reprogramó el turno |
| `APPOINTMENT_ADVANCE_UNAVAILABLE` | El horario ya no estaba disponible |
| `APPOINTMENT_ADVANCE_EXPIRED` | La oferta secuencial venció sin aceptación |
| `SYSTEM_SLOT_RELEASED` | El sistema liberó el turno |

### Atributos obligatorios

- turno y persona sujeto;
- instante efectivo;
- actor: paciente, representante, staff, efector, sistema o externo;
- canal y origen;
- motivo normalizado;
- clave de idempotencia;
- calidad de atribución;
- referencia al evento corregido cuando corresponda;
- referencias anterior/nueva para reprogramación.

No se registra texto clínico ni motivo libre dentro del perfil.

## Persistencia

### `persona_turnos_perfil`

Representa una generación inmutable:

- `id`;
- `id_persona`;
- `profile_contract_version`;
- `source_watermark_event_id`;
- `as_of`;
- `completeness_status`;
- `generated_at`;
- `superseded_at`.

### `persona_turnos_perfil_metrica`

Almacena cada medición explicable:

- `id_perfil`;
- `scope_type`: `GLOBAL`, `EFECTOR`, `SERVICIO` o `MODALIDAD`;
- `scope_id`;
- `window_days`;
- `metric_code`;
- `numerator`;
- `denominator`;
- `value`;
- `sample_size`;
- `confidence_status`.

Los snapshots no se sobrescriben silenciosamente. Una nueva generación marca la anterior como superada. El evento sigue siendo la fuente de verdad y permite reconstrucción completa.

## Métricas canónicas

| Código conceptual | Definición |
|-------------------|------------|
| Turnos cerrados elegibles | Atendidos más no-show atribuibles, excluyendo cancelados |
| Tasa de no-show | No-show atribuibles / cerrados elegibles |
| Cancelación paciente | Cancelaciones cuyo actor fue paciente o representante |
| Cancelación tardía | Cancelación paciente dentro de la ventana definida |
| Reprogramación | Cambio de cita conservando vínculo anterior/nuevo |
| Tasa de confirmación | Confirmaciones / solicitudes efectivamente entregadas |
| Asistencia confirmada | Atendidos entre turnos previamente confirmados |
| Cobertura | Outcomes con evento y atribución suficientes / outcomes observados |

Reglas:

- no producir tasas con denominador cero;
- devolver `insufficient_data` por debajo de la muestra mínima;
- consumir únicamente eventos `NATIVE` (sin backfill ni `LEGACY_INFERRED`);
- definir cancelación tardía de forma global en el contrato (no por efector);
- no atribuir al paciente acciones del sistema, staff o efector;
- usar la fecha de la cita para outcomes y la fecha del evento para tiempos de respuesta;
- no mezclar scopes sin declararlo.

## Materialización

Un servicio de dominio de Scheduling:

1. consume eventos posteriores al watermark;
2. determina personas afectadas;
3. recalcula sus ventanas y alcances;
4. persiste una generación completa;
5. actualiza el watermark sólo tras commit;
6. admite reconstrucción total y comparación determinista.

Habrá dos caminos:

- incremental después de eventos relevantes;
- batch periódico para reparación y cambios de contrato (sin backfill histórico).

## Perfil y política

El perfil no persiste `risk_level`. La política anti no-show consume:

- versión requerida del contrato;
- tamaño y confianza de muestra;
- métricas y alcance;
- contexto del turno;
- configuración del efector.

La metadata ejecutable conserva umbrales, checkpoints, acciones, salvaguardas y mensajes. `agent_run` registra:

- `profile_id`;
- versión del contrato;
- versión o hash de política;
- evidencia utilizada;
- decisión;
- acción y resultado.

## Migración de servicios

| Servicio actual | Destino |
|-----------------|---------|
| `TurnoAntinoshowRiskService` | Consumir perfil y producir decisión de política |
| `TurnoCancellationPolicyService` | Consumir cancelaciones correctamente atribuidas |
| `TurnoAgendaMetricsService` | Reutilizar definiciones canónicas y agregados |
| `TurnoLifecycleService` | Emitir todas las transiciones relevantes |
| `TurnoAntinoshowAgent` | Ejecutar y auditar; sin recalcular historial |

Durante la migración se ejecutan servicio viejo y nuevo en shadow mode. Ninguna diferencia cambia el turno hasta ser explicada.

## API y superficies

### Persona

- consulta de historial y métricas propias o representadas;
- fecha de actualización, ventana, muestra y completitud;
- explicación neutral;
- acción para solicitar corrección.

### Staff y dirección

- staff autorizado consulta hechos pertinentes y explicación de una acción concreta;
- dirección consume agregados por efector/servicio;
- cohortes pequeñas se suprimen;
- no se expone una etiqueta “paciente problemático”.

Los controllers API son delgados; autorización y composición residen en servicios de dominio. Web y móvil consumen el mismo contrato JSON y renderizan capacidades genéricas.

## Privacidad, equidad y acciones de alto impacto

Quedan prohibidos como inputs:

- diagnósticos y texto clínico;
- discapacidad;
- nacionalidad;
- nivel socioeconómico;
- dispositivo;
- geolocalización;
- proxies equivalentes.

Atributos protegidos sólo pueden utilizarse en evaluaciones agregadas autorizadas, nunca para decidir sobre una persona.

La liberación automática de cupo exige:

- modo sombra previo;
- muestra suficiente;
- confirmación efectivamente entregada;
- plazo razonable y canal alternativo;
- exclusiones de continuidad o prioridad;
- reversión o alternativa accesible;
- aprobación explícita por efector;
- medición de falsos positivos e impacto.

## Alternativas descartadas

### Columna `risk_level` en persona

Descartada porque mezcla hechos con una política mutable, pierde contexto y dificulta corrección.

### Recalcular siempre desde `turnos`

Descartada porque duplica definiciones, dificulta reproducibilidad y escala mal.

### Nuevo stream separado

Descartado para evitar divergencia. Se amplía la auditoría de turnos existente.

### ML como primera versión

Descartado hasta contar con eventos confiables, baseline, volumen suficiente y evaluación de equidad.
