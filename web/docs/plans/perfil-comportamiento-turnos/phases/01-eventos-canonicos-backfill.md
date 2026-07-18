# Fase 1 — Eventos canónicos y backfill

**Estado:** en implementación. Create/cancel/attended/no-show/corrección y backfill están implementados; faltan reprogramación, resolución, FHIR y reportes de cobertura/anomalías.

## Objetivo

Convertir las transiciones de turnos en una fuente completa, atribuible e idempotente.

## Trabajo

1. Ampliar el catálogo de `turno_evento_audit`.
2. Centralizar transiciones relevantes en servicios de lifecycle.
3. Registrar actor, origen, canal, motivo normalizado y calidad.
4. Emitir eventos de atención, no-show, corrección y reprogramación.
5. Separar cancelación paciente, staff, efector y sistema.
6. Registrar entrega/apertura de confirmación sólo cuando el canal lo demuestre.
7. Implementar backfill desde snapshots históricos de `turnos`.
8. Marcar eventos reconstruidos como `LEGACY_INFERRED`.
9. Crear reportes de cobertura y anomalías.

## Reglas de migración

- El backfill no inventa actor ni entrega de notificaciones.
- Una liberación automática nunca se registra como cancelación del paciente.
- Reprogramación conserva referencias anterior y nueva.
- Las correcciones se modelan con eventos compensatorios.
- Los eventos importados por FHIR conservan origen y confianza.

## Pruebas

- Idempotencia por clave de evento.
- Reejecución de backfill sin duplicados.
- Matriz actor × transición.
- Corrección de no-show.
- Reprogramación nativa y externa.
- Cancelación automática excluida de conducta paciente.

## Criterios de aceptación

- Todas las transiciones nuevas producen eventos.
- Los estados de turnos pueden reconciliarse con su secuencia de eventos.
- Existe medición de cobertura por período y origen.
- Cero acciones del sistema quedan atribuidas al paciente.
- El backfill puede ejecutarse y revertirse operativamente sin modificar resultados clínicos.
