# Agentes autónomos

Procesos **proactivos** en los que el sistema toma un **paso de decisión** (compromiso, matices o volumen de datos del HIS) bajo política auditable. Se distinguen **agente** (reglas + datos) y **agente IA** (el paso decisorio usa modelo).

Definición y backlog histórico: [ideas-a-futuro/agentes-autonomos-backlog.md](./ideas-a-futuro/agentes-autonomos-backlog.md).  
Plan de implementación: [planes/agentes-autonomos-implementacion.md](./planes/agentes-autonomos-implementacion.md).

---

## Patrón técnico

```mermaid
flowchart LR
  EV[Evento dominio]
  POL[YAML autonomous_agents / params]
  ENG[Motor de reglas]
  ACT[Acción dominio]
  AUD[agent_run]
  EV --> POL --> ENG
  ENG --> ACT
  ENG --> AUD
```

| Pieza | Ubicación |
|-------|-----------|
| Política declarativa | `common/metadata/bioenlace/autonomous_agents/` |
| Motor genérico | `AutonomousAgentRuleEngine` |
| Auditoría | tabla `agent_run`, `AgentRunRecorder` |
| Acciones | Servicios de dominio (push, turnos, lab, cohortes) |

---

## Agentes implementados

### D03 — Codificación automática CIE-10 / SNOMED (agente IA D2)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente IA |
| **Trigger** | `EncounterDocumentationService::guardar` |
| **Contexto IA** | `encounter-codificacion-automatica` |
| **Servicio** | `EncounterAutomaticCodingService` |
| **Efecto** | `clinical_condition` con CIE-10 y/o SNOMED (`verification_status` PROVISIONAL) |
| **Flag** | `encounter_auto_codificacion_habilitada` |

Ver [captura-clinica.md](./captura-clinica.md) y [catalogo-usos-ia.md](./catalogo-usos-ia.md).

### B01 — Rama tras respuesta de touchpoint (agente D2)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas) |
| **Trigger** | Paciente envía formulario de seguimiento (`CarePackFollowupService::submitResponses`) |
| **Política** | `autonomous_agents/care-followup-branching.yaml` |
| **Decisiones** | Empeoramiento o intensidad alta → alerta staff; adherencia baja → mensaje educativo al paciente |
| **Efecto** | Push `CARE_FOLLOWUP_STAFF_ALERT` al PES del encounter; push educativo al paciente si aplica |
| **Auditoría** | `agent_run` (`agent_id`: `care-followup-branching`) |

Requiere `care_cohort.enabled`. Ver [asistencia-cohortes.md](./asistencia-cohortes.md).

### B03 — Post-lab: clasificar y notificar (agente D2)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas LOINC) |
| **Trigger** | Ingesta nueva de `DiagnosticReport` (`LaboratoryIngestService`) |
| **Política** | `autonomous_agents/post-lab-classification.yaml` |
| **Decisiones** | normal / control / critical por analito |
| **Efecto** | Push paciente; push staff si crítico y encounter con PES |
| **Auditoría** | `agent_run` (`agent_id`: `post-lab-classification`) |

Ver [laboratorio.md](./laboratorio.md).

### A03 — Adelantamiento por cancelación (agente D2–D3)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas) |
| **Trigger** | Cancelación con slot libre ≥ T−24 h (`TurnoLifecycleService::cancelar`, masiva, FHIR inbound) |
| **Política** | `autonomous_agents/turno-advance-offer.yaml` (nearest_first, step 2 h, corte T−6 h, sin hold) |
| **Decisiones** | Oferta secuencial a turnos posteriores compatibles con push activo; una aceptación y fin |
| **Efecto** | Push `TURNO_ADVANCE_OFFER`; al aceptar, **reprograma** el turno existente bajo lock de slot |
| **API paciente** | `adelantar-oferta-como-paciente` |
| **Cron** | `yii turno-advance-offer/run` (cada 5 min); `yii turno-advance-offer/repair` (reparación) |
| **Auditoría** | `agent_run` (`agent_id`: `turno-advance-offer`) + eventos `APPOINTMENT_ADVANCE_*` |
| **Flag** | `autonomous_agent_advance_offer_enabled` |

El perfil conductual **no** ordena ni excluye candidatos; sólo puede mejorar textos de notificación de bajo impacto. Ver [turnos.md](./turnos.md).

### A02 — Negociación multicanal (agente D3, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (orquestación temporal) |
| **Trigger** | Sin respuesta tras push `TURNO_REQUIERE_REUBICACION` |
| **Política** | `autonomous_agents/turno-resolucion-multicanal.yaml` (push → email → SMS) |
| **Decisiones** | Canal siguiente; ventana horaria legal; link firmado con TTL |
| **Efecto** | Email/SMS stub con URL pública `/turno/resolucion/{token}` |
| **Cron** | `yii turno-notificacion/run` (misma cola programada) |
| **Auditoría** | `agent_run` (`agent_id`: `turno-resolucion-multicanal`) |
| **Flag** | `autonomous_agent_resolucion_multicanal_enabled` |

Ver [turnos.md](./turnos.md).

### A06 — Cierre de loop sin respuesta (agente D2, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas) |
| **Trigger** | Timeout tras push/multicanal (72 h por defecto) con resolución aún pendiente |
| **Política** | `autonomous_agents/turno-resolucion-loop-close.yaml` |
| **Decisiones** | Banda C/D → escalar staff; default → cancelar turno y liberar cupo |
| **Efecto** | Push `TURNO_RESOLUCION_SIN_RESPUESTA` o `TURNO_RESOLUCION_STAFF_ESCALATE`; adelantamiento A03 si cancela |
| **Cron** | `yii turno-notificacion/run` (`TIPO_RESOLUCION_LOOP_CLOSE`) |
| **Auditoría** | `agent_run` (`agent_id`: `turno-resolucion-loop-close`) |
| **Flag** | `autonomous_agent_resolucion_loop_close_enabled` |

Ver [turnos.md](./turnos.md).

### A04 — Anti no-show basado en reglas (agente D2, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas fijas sobre historial BD; no es ML) |
| **Trigger** | Checkpoints T−48 h y T−2 h (`programarNotificaciones` al crear/reprogramar turno) |
| **Política** | `autonomous_agents/turno-antinoshow.yaml` |
| **Decisiones** | Nivel low/medium/high calculado al vuelo; confirmación extra; liberar cupo T−24 h si alto riesgo sin confirmar |
| **Efecto** | Push `TURNO_ANTINOSHOW_CONFIRM` / recordatorio; `TURNO_ANTINOSHOW_LIBERADO` + adelantamiento A03 si aplica |
| **Cron** | `yii turno-notificacion/run` |
| **Auditoría** | `agent_run` (`agent_id`: `turno-antinoshow`) |
| **Flag** | `autonomous_agent_antinoshow_enabled` |

La v1 no materializa un perfil longitudinal ni prueba por sí sola la entrega del push. La evolución planificada separa eventos, perfil factual persistido y política; una acción automática debe registrar las versiones y evidencias consumidas.

Ver [turnos.md](./turnos.md).

### A01 — Shortlist scoreado en resolución (agente D1, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (score en metadata + BD) |
| **Trigger** | Turno pasa a `EN_RESOLUCION` y se envía push de reubicación |
| **Política** | `autonomous_agents/turno-resolucion-shortlist.yaml` |
| **Decisiones** | Top 2–3 slots (mismo PES, vecinos, proximidad) — el paciente elige y confirma |
| **Efecto** | Push `TURNO_REQUIERE_REUBICACION` con `shortlist` JSON; API `elegir-shortlist-resolucion-como-paciente` |
| **Auditoría** | `agent_run` (`agent_id`: `turno-resolucion-shortlist`) |
| **Flag** | `autonomous_agent_resolucion_shortlist_enabled` |

### A01 — Auto-reserva con preferencias (agente D2, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (score + preferencias en BD) |
| **Trigger** | Turno en `EN_RESOLUCION`, antes del push de reubicación |
| **Política** | `autonomous_agents/turno-resolucion-auto-reserva.yaml` + `turno-resolucion-shortlist.yaml` (pool) |
| **Consentimiento** | Opt-in paciente (`persona_agenda_preferencias.auto_reserva_resolucion`) + política efector |
| **Decisiones** | Un slot si score ≥ umbral y brecha vs. segundo candidato; si no → shortlist/grilla |
| **Efecto** | Push `TURNO_AUTO_REUBICADO_RESOLUCION`; API `preferencias-agenda-como-paciente` |
| **Auditoría** | `agent_run` (`agent_id`: `turno-resolucion-auto-reserva`) |
| **Flag** | `autonomous_agent_resolucion_auto_reserva_enabled` |

### H01 — Bandeja async priorizada (agente D1, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (score en metadata + datos bandeja) |
| **Trigger** | Nueva `SOLICITUD_ASYNC`, mensaje paciente, refresco bandeja staff |
| **Política** | `autonomous_agents/consulta-async-bandeja-prioridad.yaml` + SLA en `consulta_async_bandeja.yaml` |
| **Decisiones** | Orden sugerido; escalamiento push staff si SLA vencido (bandas A/B) |
| **Efecto** | Listado staff reordenado; badge prioridad; push `CONSULTA_ASYNC_SLA_ESCALATE_STAFF` |
| **Auditoría** | `agent_run` (`agent_id`: `consulta-async-bandeja-prioridad`) |
| **Flag** | `autonomous_agent_consulta_async_prioridad_enabled` |

Ver [atencion-remota-async.md](./atencion-remota-async.md).

### E01 — Asociar lab a encounter (agente D2, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (score + pedidos en BD) |
| **Trigger** | Ingesta de `DiagnosticReport` sin referencia FHIR unívoca |
| **Política** | `autonomous_agents/lab-encounter-link.yaml` |
| **Decisiones** | Auto-vincular si score + brecha; si no → bandeja staff |
| **API staff** | `listar-pendientes-vincular-como-staff`, `vincular-informe-a-encounter-como-staff` |
| **Auditoría** | `agent_run` (`agent_id`: `lab-encounter-link`) |
| **Flag** | `autonomous_agent_lab_encounter_link_enabled` |

Ver [laboratorio.md](./laboratorio.md).

### E02 — Reintentos integración (agente D3, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (auditoría sobre cola existente) |
| **Trigger** | Job FHIR HC fallido o dead-letter (`ClinicalHistoryOutboundProcessorService`) |
| **Política** | `autonomous_agents/integration-retry.yaml` + `clinicalHistoryExchange.retry` |
| **Efecto** | `agent_run`; log/push ops si `integrationRetry.ops_persona_ids` |
| **Flag** | `autonomous_agent_integration_retry_enabled` |

Ver [interoperabilidad-historia-clinica.md](./interoperabilidad-historia-clinica.md). RDI/LIS: extensión futura cuando exista cola outbound.

### A05 — Ruteo post-triage sin cupo (agente D1, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas triage + modalidad) |
| **Trigger** | Grilla de slots vacía tras triage de reserva |
| **Política** | `autonomous_agents/reserva-triage-post-cupo-routing.yaml` |
| **Decisiones** | async / tele hub / primaria / lista de espera / halt banda A |
| **Efecto** | Push `RESERVA_TRIAGE_CANAL_ALTERNATIVO` + `routing_recommendation` en API |
| **Auditoría** | `agent_run` (`agent_id`: `reserva-triage-post-cupo-routing`) |
| **Flag** | `autonomous_agent_reserva_triage_post_cupo_enabled` |

Ver [turnos.md](./turnos.md), [triage-reserva-turno.md](./triage-reserva-turno.md).

### B02 — Seguimiento post-alta (agente D2, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas + touchpoints programados) |
| **Trigger** | Alta hospitalaria (`InternacionAltaEstructuradaService::registrarAlta`) |
| **Política** | `autonomous_agents/post-discharge-followup.yaml` |
| **Decisiones** | Programa default o cirugía; touchpoints días 1, 7, 30 |
| **Efecto** | Cola `care_followup_touchpoint_queue`; rama B01 en respuestas |
| **Auditoría** | `agent_run` (`agent_id`: `post-discharge-followup`) |
| **Flag** | `autonomous_agent_post_discharge_followup_enabled` |

Ver [internacion.md](./internacion.md).

### E03 — Validar receta pre-envío RDI (agente D2, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas de validación) |
| **Trigger** | `ElectronicPrescriptionService::issue` antes de emitir |
| **Política** | `autonomous_agents/prescription-rdi-pre-submit.yaml` |
| **Decisiones** | Bloquear si faltan PES, diagnóstico, código, posología o duplicado 24 h |
| **Efecto** | `InvalidArgumentException` al prescriptor; no se emite |
| **Auditoría** | `agent_run` (`agent_id`: `prescription-rdi-pre-submit`) |
| **Flag** | `autonomous_agent_prescription_rdi_validation_enabled` |

Ver [receta-electronica.md](./receta-electronica.md).

### F02 — Sugerencia de cama (agente D1, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (score sobre mapa de camas) |
| **Trigger** | Contexto de ingreso (`InternacionIngresoService::contextoIngreso`) |
| **Política** | `autonomous_agents/internacion-cama-sugerencia.yaml` |
| **Decisiones** | Top N camas por O2, aislamiento, pediatría, servicio |
| **Efecto** | Campo `cama_sugerencias` en API ingreso; humano confirma |
| **Auditoría** | `agent_run` (`agent_id`: `internacion-cama-sugerencia`) |
| **Flag** | `autonomous_agent_internacion_cama_sugerencia_enabled` |

Ver [internacion.md](./internacion.md).

---

## En implementación / backlog (sin agentes IA)

Agentes IA (C03, D02, redacción pushes): diferidos.

---

## Documentos relacionados

- [Turnos](./turnos.md) · [Laboratorio](./laboratorio.md) · [Asistencia cohortes](./asistencia-cohortes.md)
- [Catálogo usos IA](./catalogo-usos-ia.md) · [Costos API](../costos/costos-api.md)
