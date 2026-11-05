# Fase 1 — Eventos canónicos

**Estado:** casi completa. Create/cancel/reprogram/resolución/attended/no-show/corrección/FHIR/confirmación (solicitada/entregada/abierta)/adelantamiento (`OFFERED`/`DELIVERED`/`OPENED`/`ACCEPTED`/`UNAVAILABLE`/`EXPIRED`) implementados. Sin backfill ni `LEGACY_INFERRED` (sin retrocompatibilidad). Faltan reportes de cobertura/anomalías.

## Objetivo

Convertir las transiciones de turnos en una fuente completa, atribuible e idempotente, **sólo con evidencia nativa** desde el corte de la implementación canónica.

## Trabajo

1. Ampliar el catálogo de `turno_evento_audit`.
2. Centralizar transiciones relevantes en servicios de lifecycle.
3. Registrar actor, origen, canal, motivo normalizado y calidad `NATIVE`.
4. Emitir eventos de atención, no-show, corrección y reprogramación.
5. Separar cancelación paciente, staff, efector y sistema.
6. Registrar entrega/apertura de confirmación sólo con evidencia real del canal/cliente.
7. ~~Backfill histórico~~ — **fuera de alcance**: no hay reconstrucción desde snapshots legacy.
8. Crear reportes de cobertura y anomalías sobre el stream nativo.

## Reglas

- No se inventan eventos a partir de estados históricos previos al corte.
- Una liberación automática nunca se registra como cancelación del paciente.
- Reprogramación conserva referencias anterior y nueva.
- Las correcciones se modelan con eventos compensatorios.
- Los eventos importados por FHIR conservan origen externo y calidad `NATIVE` cuando el sync los emite de forma explícita.

## Pruebas

- Idempotencia por clave de evento.
- Matriz actor × transición.
- Corrección de no-show.
- Reprogramación nativa y externa.
- Cancelación automática excluida de conducta paciente.
- Eventos con calidad distinta de `NATIVE` no alimentan el perfil.

## Criterios de aceptación

- Todas las transiciones nuevas producen eventos nativos.
- El perfil materializado ignora cualquier evidencia no nativa.
- No existe comando operativo de backfill del perfil.
