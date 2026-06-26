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

### A03 — Lista de espera / relleno de huecos (agente D2–D3, v1 FIFO)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas) |
| **Trigger** | Cancelación de turno con hueco liberado (`TurnoLifecycleService::cancelar`) |
| **Política** | `autonomous_agents/turno-waitlist-fill.yaml` (FIFO, TTL 15 min, no ofertar banda A) |
| **Decisiones** | Primer inscripto en cola; si no acepta en TTL → siguiente |
| **Efecto** | Push `TURNO_WAITLIST_OFFER`; al aceptar, crea turno en el slot liberado |
| **API paciente** | `lista-espera-inscribir/cancelar/estado/aceptar-oferta-como-paciente` |
| **Cron** | `yii turno-waitlist/expire-offers` (cada 1–5 min junto a notificaciones) |
| **Auditoría** | `agent_run` (`agent_id`: `turno-waitlist-fill`) |
| **Flag** | `autonomous_agent_waitlist_enabled` |

Ver [turnos.md](./turnos.md).

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
| **Efecto** | Push `TURNO_RESOLUCION_SIN_RESPUESTA` o `TURNO_RESOLUCION_STAFF_ESCALATE`; waitlist A03 si cancela |
| **Cron** | `yii turno-notificacion/run` (`TIPO_RESOLUCION_LOOP_CLOSE`) |
| **Auditoría** | `agent_run` (`agent_id`: `turno-resolucion-loop-close`) |
| **Flag** | `autonomous_agent_resolucion_loop_close_enabled` |

Ver [turnos.md](./turnos.md).

### A04 — Anti no-show predictivo (agente D2, v1)

| Campo | Valor |
|-------|--------|
| **Tipo** | Agente (reglas sobre historial BD) |
| **Trigger** | Checkpoints T−48 h y T−2 h (`programarNotificaciones` al crear/reprogramar turno) |
| **Política** | `autonomous_agents/turno-antinoshow.yaml` |
| **Decisiones** | Score low/medium/high; confirmación extra; liberar cupo T−24 h si alto riesgo sin confirmar |
| **Efecto** | Push `TURNO_ANTINOSHOW_CONFIRM` / recordatorio; `TURNO_ANTINOSHOW_LIBERADO` + waitlist A03 |
| **Cron** | `yii turno-notificacion/run` |
| **Auditoría** | `agent_run` (`agent_id`: `turno-antinoshow`) |
| **Flag** | `autonomous_agent_antinoshow_enabled` |

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

---

## En implementación / backlog

| ID | Nombre | Fase plan |
|----|--------|-----------|
| H01, E01, E02, C03, D02, F02 | Ver plan | 2–4 |

---

## Documentos relacionados

- [Turnos](./turnos.md) · [Laboratorio](./laboratorio.md) · [Asistencia cohortes](./asistencia-cohortes.md)
- [Catálogo usos IA](./catalogo-usos-ia.md) · [Costos API](../costos/costos-api.md)
