# Domain/Model

Aggregates del módulo Encounter (fase 5 piloto).

| Clase | Estado |
|-------|--------|
| `Encounter` / `EncounterId` | Activo — `open` / `finish` / `cancel`; wired en `EncounterLifecycleService` |
| `ChiefComplaintReason` | Activo — normalización motivos CC; wired en `EncounterReasonService` |
| `Condition` | Activo — `transitionTo`; wired en `ConditionLifecycleService` |
| `AppointmentReason` | Stub — chat pre-consulta aún en Application |

Sin I/O ni Yii aquí. Application reconstituye desde AR, aplica mutaciones y persiste.
