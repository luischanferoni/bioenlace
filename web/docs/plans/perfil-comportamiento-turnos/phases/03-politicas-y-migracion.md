# Fase 3 — Políticas y migración

**Estado:** parcial en shadow. A04 y cancelación adjuntan candidato factual; KPIs de agenda ya leen eventos canónicos; liberación automática deshabilitada. Faltan unificación de checkpoints y promoción controlada de políticas.

## Objetivo

Hacer que anti no-show, cancelaciones y KPIs consuman hechos comunes sin mezclar perfil con decisión.

## Trabajo

1. Versionar el contrato de política anti no-show.
2. Mover al perfil las definiciones de features históricas.
3. Mantener en metadata umbrales, checkpoints, acciones y salvaguardas.
4. Migrar `TurnoAntinoshowRiskService` para consumir un snapshot.
5. Migrar `TurnoCancellationPolicyService` para usar eventos atribuibles.
6. Migrar `TurnoAgendaMetricsService` a definiciones canónicas.
7. Registrar en `agent_run` perfil, contrato, política, evidencia y resultado.
8. Unificar checkpoints para evitar notificaciones duplicadas.
9. Ejecutar cálculo viejo y nuevo en shadow mode.

## Salvaguardas

- Una muestra insuficiente no habilita liberación automática.
- Falta de confirmación entregada no se interpreta como falta de respuesta.
- Cancelaciones de sistema, staff o efector no penalizan autogestión.
- Primera visita y continuidad priorizada quedan fuera de acciones de alto impacto.
- Una decisión sin perfil vigente falla de manera conservadora.

## Comparación

Cada decisión en shadow mode registra:

- salida del servicio vigente;
- salida de la política nueva;
- hechos y scope utilizados;
- motivo de diferencia;
- acción que se habría ejecutado.

## Criterios de aceptación

- Diferencias históricas clasificadas y explicadas.
- `agent_run` permite reproducir una decisión.
- KPIs y políticas comparten denominadores.
- La política de cancelación excluye eventos no atribuibles.
- La liberación automática permanece deshabilitada hasta la fase 5.
- La metadata ejecutable sólo cambia junto con servicios y tests.
