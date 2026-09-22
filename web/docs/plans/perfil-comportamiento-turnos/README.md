# Plan — Perfil persistido de comportamiento en turnos

| Campo | Valor |
|-------|-------|
| Slug | `perfil-comportamiento-turnos` |
| Estado | **Construcción V1 cerrada — shadow operativo; piloto fase 5 pendiente** |
| Dominio | Scheduling |
| Objetivo | Materializar un perfil factual, explicable, corregible y versionado a partir de eventos de turnos |

## Principio

El perfil describe hechos observados; no califica moralmente a una persona ni decide por sí mismo una restricción. Las políticas de recordatorio, confirmación, lista de espera o liberación de cupos consumen el perfil, aplican reglas declarativas y registran la decisión resultante.

Las preferencias declaradas por la persona permanecen separadas del comportamiento observado.

## Índice

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Problema, alcance, resultados y métricas de éxito |
| [design.md](./design.md) | Eventos, persistencia, materialización, políticas, API y seguridad |
| [Fase 0](./phases/00-marco-privacidad-equidad.md) | Marco de producto, privacidad, equidad y glosario |
| [Fase 1](./phases/01-eventos-canonicos-backfill.md) | Eventos canónicos nativos (sin backfill) |
| [Fase 2](./phases/02-perfil-materializado.md) | Tablas, métricas, versionado y materializador |
| [Fase 3](./phases/03-politicas-y-migracion.md) | Migración de anti no-show, cancelaciones y KPIs |
| [Fase 4](./phases/04-api-ui-transparencia.md) | API, permisos, UI y corrección |
| [Fase 5](./phases/05-piloto-evaluacion-cierre.md) | Shadow mode, piloto, evaluación y cierre |

## Dependencias actuales

- Historial de turnos y estados.
- Auditoría de eventos de turno.
- `TurnoAntinoshowRiskService`.
- `TurnoCancellationPolicyService`.
- `TurnoAgendaMetricsService`.
- Ejecuciones auditadas en `agent_run`.
- Preferencias en `persona_agenda_preferencias`.
- Metadata `agents/turno-antinoshow.yaml`.

## Orden de ejecución

Las fases 0 y 1 son bloqueantes. No se habilita ninguna decisión nueva sobre pacientes hasta completar eventos nativos, atribución y evaluación en shadow mode. Las fases 2 y 3 pueden avanzar parcialmente en paralelo una vez cerrado el contrato de eventos. No hay backfill ni evidencia `LEGACY_INFERRED`.

## Estado de implementación (2026-09-22)

- Implementado: contrato V1 (sólo `NATIVE`), stream canónico, materializador, create/cancel/reprogram/resolución/attended/no-show/corrección/FHIR/confirmación (solicitada/entregada/abierta)/adelantamiento (`APPOINTMENT_ADVANCE_*`) / `SYSTEM_SLOT_RELEASED` en path de release A04.
- Sin backfill histórico: el perfil empieza en el corte de eventos nativos.
- Cancelación tardía: definición **global** del contrato (`hours_before_appointment`), no por efector.
- KPIs de agenda desde eventos canónicos nativos.
- Shadow comparable: A04/cancelación adjuntan candidato con ventana del contrato (`nearestWindowDays`) y `diff_reason` en `agent_run`.
- Liberación deshabilitada (`execution_mode: shadow`, `release_slot.enabled: false`).
- API + UI JSON: historial propio/representado, explicación, agregado staff, solicitud y resolución de corrección (RBAC de corrección en migración `m260922_120000_*`).
- Reportes: `php yii turno-behavior-profile/coverage`.
- Fuera de alcance operativo: piloto formal de fase 5 (calibración con datos reales / enforce).

## Cierre del plan

Al completar el piloto (fase 5):

1. Consolidar la narrativa vigente en `producto/turnos.md` (ya actualizada para V1+shadow).
2. Registrar decisiones transversales estables en `decisions/` si corresponde.
3. Actualizar la madurez en `his-completo/11-agenda-turnos.md`.
4. Eliminar esta carpeta temporal.
