# Overview — Interoperabilidad historia clínica

## Objetivo

Exportar **documentación clínica estructurada** (FHIR Bundle) hacia redes de salud / servidores gubernamentales cuando un encuentro queda **finalizado**, con cola durable, reintentos y auditoría — **sin acoplar** orquestadores a un `intent_id` ni a un endpoint concreto.

El **bloqueo externo** restante es el **contrato y credenciales** del receptor nacional (Fase 0 / homologación). El código de POST, mapper y cola ya está en repo.

## Direcciones de flujo

| Dirección | Qué | Cuándo (producto) | Fase |
|-----------|-----|-------------------|------|
| **Saliente (push)** | Bundle FHIR de un `Encounter` finalizado | Tras `EncounterLifecycleService::finalize` (AMB, EMER, IMP según config) | 1–3 |
| **Saliente (batch)** | Reintento de jobs fallidos | Cron `clinical-history-exchange/process-outbound` | 1 |
| **Saliente (reconcile)** | Acuse / `external_id` definitivo | Cron `clinical-history-exchange/reconcile` (requiere `statusPath`) | 4 |
| **Entrante (pull)** | Resultados lab, MPI, etc. | Ya existe en otros módulos (`Laboratory`, `Mpi`) | — |
| **Entrante (webhook)** | Acuse / id externo / corrección | Cuando el nacional confirme recepción | 4 |

## Actores

- **Bioenlace** — genera Bundle, encola, envía, audita.
- **Red / ministerio** — recibe Bundle, devuelve `external_id` o error normativo.
- **Efector** — puede filtrar export por `efector_id` (config).
- **Auditoría** — staff con permiso API consulta estado de jobs (`HistoryExchangeController`).

## Modos de producto

| Modo | Descripción |
|------|-------------|
| **A — Off** | `clinicalHistoryExchange.enabled = false` (default). Sin cola ni envío. |
| **B — Cola dry-run** | Encola y mapea Bundle; conector `null` marca `OMITIDO`. Para QA del mapper. |
| **C — Nacional** | Conector `nacional-fhir` con `enabled=true` y credenciales en `params-local`. |

## Fuera de alcance inicial

- IPS / Resumen internacional del paciente como producto separado.
- Sustituir expediente legal PDF por FHIR.
- Bidireccional clínica completa (solo acuse en Fase 4).
- ETL de datos legacy pre-FHIR.

## Criterio de éxito Fase 1

1. Al finalizar encounter AMB, si `enabled`, existe fila en `clinical_history_outbound_job`.
2. Cron procesa la cola; conector null → `OMITIDO` con mensaje claro.
3. Documentación de flujo y reintentos en [design.md](./design.md).
