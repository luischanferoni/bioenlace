# Fase 03 — Enum / Domain / Agents al módulo dueño

**Estado: hecha** (catch-all `Service/` aún parcial).

## Hecho

- Flows Clinical bajo módulo; `Clinical/Application/` eliminado
- Enums → `*/Domain/` (Encounter, CarePlan, Emergency, CareCohort, Prescription)
- Agents + policies → `*/Application/Agent/` (Laboratory, Inpatient, CareCohort, Prescription, HistoryExchange)
- Catalogs journey/motivos → `Encounter/Domain/`; `PedidoAtencionCatalog` → `PedidoAtencion/Domain/`
- `EncounterJourney/*` services → `Encounter/Service/EncounterJourney/`
- Shape test reconoce `Modulo/Application/Agent`

## Pendiente (oleadas siguientes / fase 03b)

- Adelgazar `Clinical/Service/` catch-all (lifecycle, care plan residual, etc.) hacia módulos
- Reclasificar `Workflow/`, `Capture/`, `Text/`, `AiContext/`, `Dto/`
