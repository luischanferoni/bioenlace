# Plan — Auditoría agentes autónomos (admin)

| Campo | Valor |
|-------|--------|
| Slug | `auditoria-agentes-autonomos` |
| Estado | En ejecución |
| Superficie | Admin Yii, solo superadmin |

## Módulos

1. **Agent run (genérico)** — listado/filtro/detalle de `agent_run` (todos los `agent_id`)
2. **Anti no-show (A04)** — `turno_notificacion_programada` ANTINOSHOW_* + runs/eventos
3. **Resolución (A01/A02/A06)** — runs de shortlist, auto-reserva, multicanal, loop-close + notifs

A03 y captura clínica ya tienen módulos propios.
