# Fase 1 — Estructura, cola y cron

## Estado

| Ítem | Estado |
|------|--------|
| Tabla `clinical_history_outbound_job` | Implementado |
| Tabla `clinical_history_outbound_audit` | Implementado |
| `ClinicalHistoryOutboundEnqueueService` | Implementado |
| Hook en `EncounterLifecycleService::finalize` | Implementado |
| `ClinicalHistoryOutboundProcessorService` | Implementado |
| Conector `null` | Implementado |
| Conector HTTP nacional | OAuth + POST implementado; `submitPath` configurable (contrato TBD) |
| Mapper FHIR | Esqueleto v1 con Patient, Encounter, Composition, Condition, pedidos, alergias, lab |
| Cron consola | Implementado |

## Flujo temporal (cuándo se envía)

```mermaid
sequenceDiagram
  participant Med as Médico / API
  participant Life as EncounterLifecycleService
  participant Enq as OutboundEnqueueService
  participant Q as outbound_job
  participant Cron as yii process-outbound
  participant Proc as OutboundProcessorService
  participant Conn as ExchangeConnector

  Med->>Life: close / finalize encounter
  Life->>Life: status = finished
  Life->>Enq: scheduleIfApplicable(encounter)
  alt enabled y clase permitida
    Enq->>Q: INSERT PENDIENTE run_at = now + delay
  end
  Note over Q: Espera delay_after_finalize (default 2 min)
  Cron->>Proc: processDueQueue(limit)
  Proc->>Q: SELECT PENDIENTE/FALLIDO run_at <= now
  Proc->>Proc: build Bundle (mapper)
  Proc->>Conn: submitEncounterBundle
  alt conector null
    Conn-->>Proc: OMITIDO
  else HTTP futuro
    Conn-->>Proc: ENVIADO + external_id
  end
  Proc->>Q: UPDATE estado
```

## Cuándo **no** se encola

- `clinicalHistoryExchange.enabled = false`
- `encounter_class` no está en `encounter_classes`
- `efector_id` en lista de exclusión (`excluded_efector_ids`)
- Encounter sin `subject_persona_id`
- Ya existe job `ENVIADO` para el mismo `(encounter_id, profile)`

## Cron

```bash
# Procesar cola (producción: cada 5 min en crontab)
php yii clinical-history-exchange/process-outbound

# Opcional: límite
php yii clinical-history-exchange/process-outbound 50

# Un job por id (soporte)
php yii clinical-history-exchange/process-one 123
```

## Checklist Fase 1

- [x] Migración + modelos
- [x] Servicios enqueue / processor
- [x] Registry + conectores null / http stub
- [x] Params `clinicalHistoryExchange`
- [x] Hook finalize encounter
- [x] Mapper Bundle v1 (recursos clínicos del encounter)
- [x] API staff listar/ver estado jobs
- [ ] Tests unitarios enqueue + retry backoff (parcial)
- [x] RBAC lectura estado jobs
