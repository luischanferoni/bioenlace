# Fase 2 — Migrar intents

## Objetivo

Cada intent con `subintents` pasa a `states`. Se empieza por Solicitar Atención, que es el grafo con bifurcación y reconvergencia.

## Orden

1. `atencion.necesito-atencion` (raíz exclusiva, colas de reserva repetidas, cierre por rama).
2. El resto de flows de paciente (turnos, resultados, representación).
3. Flows de staff (agenda, PES, guardia, internación).
4. Intents de plataforma y DataAccess que tengan pasos.

## Regla por archivo

- Escribir `initial`, `states`, `always`, `description`, `meta`, `type: final`.
- Borrar `subintents` de ese archivo.
- `draft_keys_extra` pasa a `context` en el mismo cambio.
- Corrida del test de ese flow si existe (`SolicitarAtencionTriageFlowTest`, `ConsultasSeguimientoFlowYamlTest`, y los que nombren el intent).

## Checklist

- [x] Solicitar Atención
- [x] Resto de intents paciente
- [x] Intents staff y plataforma
- [x] Ningún YAML de intent declara `subintents`
