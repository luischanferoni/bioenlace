# Plan — Auditoría adelantamiento de turnos (A03)

| Campo | Valor |
|-------|--------|
| Slug | `auditoria-adelantamiento-turnos` |
| Estado | En ejecución — Fase 0 (admin read-only) |
| Dueño | Equipo scheduling / plataforma |
| Superficie | Admin Yii, solo superadmin |

## Índice

- [design.md](./design.md) — pantallas, fuentes de datos, KPIs futuros

## Código existente

| Área | Ubicación |
|------|-----------|
| Campaña / ofertas | `turno_advance_campaign`, `turno_advance_offer` |
| Agente | `TurnoAdvanceOfferAgent` |
| Eventos | `TurnoEventoAudit` `APPOINTMENT_ADVANCE_*` |
| agent_run | `AgentRunRecorder` (agent_id `turno-advance-offer`) |
| Patrón admin | `CapturaClinicaAuditController` |

## Al cerrar

Volcar a `producto/turnos.md` / `agentes-autonomos.md` y borrar esta carpeta.
