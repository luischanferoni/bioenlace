# Diseño — Auditoría agentes autónomos

## Genérico (`/agent-run-audit`)

Filtros: `agent_id`, `outcome`, `trigger_type`, persona, fechas. Detalle con facts/decision JSON.

Catálogo de agentes: YAML en `autonomous_agents/` ∪ distinct en BD.

## A04 (`/antinoshow-audit`)

Listado de notificaciones `ANTINOSHOW_CHECKPOINT` / `ANTINOSHOW_RELEASE`. Fallos: `FALLIDA` o `PENDIENTE` vencida. Detalle: notif + `agent_run` (`turno-antinoshow`) + eventos CONFIRMATION_* / SYSTEM_SLOT_RELEASED del turno.

## Resolución (`/resolucion-audit`)

Listado `agent_run` de:

- `turno-resolucion-shortlist`
- `turno-resolucion-auto-reserva`
- `turno-resolucion-multicanal`
- `turno-resolucion-loop-close`

Detalle: run + notifs `RESOLUCION_MULTICANAL` / `RESOLUCION_LOOP_CLOSE` del `trigger_id` (turno) cuando aplica.
