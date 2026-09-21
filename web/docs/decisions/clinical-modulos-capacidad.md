# Clinical: módulos de capacidad (sin Shared)

**Estado:** aceptado.

## Contexto

Tras colocalizar capas DDD a nivel BC (`Application/` / `Domain/` en la raíz de `Clinical`), los flows y enums quedaban lejos de la capacidad de negocio (`Emergency/`, `Laboratory/`, …). Carpetas técnicas (`Enum/`, `Service/`, `Dto/`) mezclaban conceptos de varios dueños. Una carpeta `Shared/` hubiera repetido el mismo anti-patrón.

## Decisión

1. Dentro de `Domain/Clinical`, el **primer eje es el módulo de capacidad** (Encounter, Emergency, Inpatient, Laboratory, Prescription, CarePlan, CareCohort, PedidoAtencion, Capture, HistoryExchange, LegalRecord, Specialty).
2. Las capas DDD viven **dentro** del módulo. Bajo `Application/`: **solo roles CA** (`UseCase/`, `Presentation/`, …); el dominio va en **nombres de clase** y en `Domain/` ([ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md)).
3. **No** hay `Shared/` / `Support/` / `Checkpoint/` (etc.) en la raíz del BC ni carpetas de capacidad bajo `Application/`. Shared de producto: `components/Shared/`.
4. Todo tipo tiene **módulo dueño**.
5. Discovery de intents: `Domain/<BC>/<Modulo>/Application/Flows/intents`.
6. Plugins BC: `Assistant/`, `Home/`, `DataAccess/`.
7. Piloto packaging: `Clinical/Capture`.

## Alternativas descartadas

- Layer-first permanente en la raíz Clinical.
- Partir Clinical en varios BCs (comparten Encounter/CarePlan).
- Carpeta `Shared/` **dentro** de Clinical para enums “transversales”.
- `Clinical/Infrastructure/` catch-all (LIS + receta + HC).

## Consecuencias

- Localidad: el flow/agent/enum de lab se busca bajo `Laboratory/`.
- Otros BCs grandes pueden adoptar el mismo patrón módulo-primero cuando el layer-first deje de escalar.
- Relacionado: [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md), [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md), [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md), [domain-folder-grammar.md](./domain-folder-grammar.md), `Domain/Clinical/README.md`.
