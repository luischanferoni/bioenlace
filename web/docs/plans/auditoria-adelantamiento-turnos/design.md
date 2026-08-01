# Diseño — Auditoría adelantamiento (A03)

## Objetivo

Ver desde admin (superadmin) si, tras una cancelación, el agente de adelantamiento ofreció el hueco (D+2 / D+1), notificó al paciente y si éste aceptó o la oferta venció.

## Fuentes (sin schema nuevo)

- `turno_advance_campaign` — estado de la campaña por slot cancelado
- `turno_advance_offer` — secuencia PENDING / ACCEPTED / EXPIRED / …
- `turno_evento_audit` — `APPOINTMENT_ADVANCE_OFFERED|DELIVERED|OPENED|ACCEPTED|EXPIRED|UNAVAILABLE`
- Opcional en detalle: `agent_run` con `agent_id = turno-advance-offer`

## Pantallas v1

| Ruta | Uso |
|------|-----|
| `/adelantamiento-audit/index` | Listado de campañas |
| `/adelantamiento-audit/fallos` | STOPPED, EXHAUSTED, ACTIVE con next_run vencido |
| `/adelantamiento-audit/view` | Campaña + ofertas + timeline de eventos |

## Fuera de v1

KPIs agregados, cola “canceló y no hubo campaña”, reprogramación manual del paciente.

## Siguientes agentes (prioridad sugerida post-A03)

| Prioridad | Agente | Por qué | Datos ya listos |
|-----------|--------|---------|-----------------|
| 1 | `turno-antinoshow` (A04) | Alto impacto (libera cupos); hay que ver confirmación vs liberación | `agent_run` + notifs programadas + eventos CONFIRMATION_* / SYSTEM_SLOT_RELEASED |
| 2 | `turno-resolucion-loop-close` (A06) + multicanal (A02) | Cierra o escala resolución; afecta demanda | `agent_run` + `turno_notificacion_programada` |
| 3 | `turno-resolucion-shortlist` / `auto-reserva` (A01) | ¿elige shortlist o auto-reubica? | `agent_run` |
| 4 | Viewer genérico `agent_run` | Un listado filtrable por `agent_id` cubre el resto (lab, RDI, async, cama) | tabla `agent_run` |

Captura clínica y A03 quedan como módulos de dominio ricos; el resto puede empezar con un **AgentRunAuditController** genérico.
