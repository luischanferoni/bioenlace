# Fase 01 — Clinical: resto de módulos

**Estado:** en curso (Application de todos los módulos Clinical alineada a roles CA; quedan naming puntual y Domain building blocks)  
**Depende de:** piloto `Capture` (hecho)

## Objetivo

Alinear todos los módulos bajo `Domain/Clinical/*` al mismo eje que Capture: `Application/{UseCase,Presentation,Service,…}`, sufijos transversales, sin carpetas de capacidad bajo Application.

## Checklist por módulo

Orden sugerido (mayor deuda primero):

- [x] **Encounter** — aplanar `Documentation/`, `AiContext/`, `EncounterJourney/`, `PatientSummary/`, `Dto/`; classes → `*Service` / UseCase / Presenter; quitar metáforas (`*Orchestrator`, `*Formatter`, …)
- [x] **CarePlan** — `Dto/`, `Reminder/` → Service/UseCase; revisar `*Processor` / `*Enqueuer`
- [x] **Laboratory** / **Prescription** / **Inpatient** — `Dto/` + naming
- [x] **Emergency**, **PedidoAtencion**, **Specialty**, **HistoryExchange**, **LegalRecord**, **CareCohort**, **Home** — inventario + fixes puntuales

## Pendiente fino (misma fase)

- [ ] Revisar nombres aún metafóricos embebidos (`*ProcessorService`, `*SchedulerService`, parsers internos)
- [ ] Completar building blocks Domain (`Catalog/` / `Policy/` / `Port/`) donde sigan enums planos
- [ ] UseCase/ explícitos donde hoy hay solo `*Service` de orquestación de entrada
- [ ] Tests unitarios con PHP ≥ 8.1

## Por cada módulo

1. Inventario: carpetas Application ≠ rol CA; clases con sufijo fuera de catálogo.
2. Matriz rename (path + FQCN + tests).
3. Mover a `UseCase/` | `Presentation/` | `Service/` | `Authorization/` | `Flows/` | `Agents/`.
4. Domain: `*Validator` Application → `*Policy` si es regla sin I/O (ports OK).
5. Actualizar `Clinical/<Modulo>/README.md` si existe.
6. PR acotado al módulo.

## No hacer en esta fase

- Reshape de Organization / Scheduling / Person.
- Cambiar URLs API.
