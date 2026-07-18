# Plan — Perfil persistido de comportamiento en turnos

| Campo | Valor |
|-------|-------|
| Slug | `perfil-comportamiento-turnos` |
| Estado | **En implementación — base V1 y shadow mode** |
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
| [Fase 1](./phases/01-eventos-canonicos-backfill.md) | Eventos canónicos, atribución y reconstrucción histórica |
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
- Metadata `autonomous_agents/turno-antinoshow.yaml`.

## Orden de ejecución

Las fases 0 y 1 son bloqueantes. No se habilita ninguna decisión nueva sobre pacientes hasta completar eventos, atribución, backfill y evaluación en shadow mode. Las fases 2 y 3 pueden avanzar parcialmente en paralelo una vez cerrado el contrato de eventos.

## Estado de implementación (2026-07-18)

- Implementado: contrato V1 declarativo, stream canónico idempotente, snapshots de cita, backfill y materializador versionado.
- Integrado: creación, cancelación, reprogramación, resolución, atención, no-show, corrección, FHIR inbound, confirmación solicitada y waitlist offered/accepted.
- KPIs: `TurnoAgendaMetricsService` consume eventos canónicos (compatibilidad de respuesta preservada + cobertura).
- Seguridad inmediata: A04/A06 atribuyen cancelaciones automáticas al sistema; liberación A04 deshabilitada y limitada a `enforce`.
- Shadow: anti no-show y cancelación calculan evidencia candidata desde el perfil sin cambiar todavía la decisión legacy.
- Transparencia: historial propio/representado, explicación de acción propia y agregado de efector con supresión de cohortes pequeñas.
- Pendiente: entrega real de confirmación (`CONFIRMATION_DELIVERY_CONFIRMED`), circuito de solicitud de corrección, UI JSON compartida, unificación de checkpoints y piloto.

## Cierre del plan

Al completar el programa:

1. Consolidar la narrativa vigente en `producto/turnos.md`.
2. Registrar decisiones transversales estables en `decisions/` si corresponde.
3. Actualizar la madurez en `his-completo/11-agenda-turnos.md`.
4. Eliminar esta carpeta temporal.
