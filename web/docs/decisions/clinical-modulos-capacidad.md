# Clinical: módulos de capacidad (sin Shared)

**Estado:** aceptado.

## Contexto

Tras colocalizar capas DDD a nivel BC (`Application/` / `Domain/` en la raíz de `Clinical`), los flows y enums quedaban lejos de la capacidad de negocio (`Emergency/`, `Laboratory/`, …). Carpetas técnicas (`Enum/`, `Service/`, `Dto/`) mezclaban conceptos de varios dueños. Una carpeta `Shared/` hubiera repetido el mismo anti-patrón.

## Decisión

1. Dentro de `Domain/Clinical`, el **primer eje es el módulo de capacidad** (Encounter, Emergency, Inpatient, Laboratory, Prescription, CarePlan, CareCohort, PedidoAtencion, Capture, HistoryExchange, LegalRecord, Specialty).
2. Las capas DDD viven **dentro** del módulo: `Application/` · `Domain/` · `Infrastructure/` (más plugins `Flows`/`Agents`/`Presentation` según gramática). Subcarpetas internas: **capacidad / lenguaje ubicuo** — ver [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md) §4 y [domain-folder-grammar.md](./domain-folder-grammar.md).
3. **No** hay `Shared/` / `Common/` / `Kernel/` ni `Enum/` / `Service/` / `Dto/` / `Infrastructure/` / `Support/` en la **raíz** del BC Clinical. Shared de producto va en `components/Shared/` ([shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md)). ACL externos van en `<Modulo>/Infrastructure/External/`.
4. Todo tipo tiene **módulo dueño**. Lo “usado por muchos” pertenece al núcleo (`Encounter/` o `CarePlan/`); los periféricos dependen hacia el núcleo, no al revés.
5. Discovery de intents: `Domain/<BC>/<Modulo>/Application/Flows/intents` (además del layout plano BC si queda en otros BCs). `domainFromPath` sigue devolviendo el BC (`clinical`).
6. Adapters al motor Platform (`Assistant/`, `Home/`, `DataAccess/`) en la raíz del BC son plugins, no dominio compartido.
7. Piloto de Application fino + eje por capacidad: `Clinical/Capture` ([Capture/README.md](../../common/components/Domain/Clinical/Capture/README.md)).

## Alternativas descartadas

- Layer-first permanente en la raíz Clinical.
- Partir Clinical en varios BCs (comparten Encounter/CarePlan).
- Carpeta `Shared/` **dentro** de Clinical para enums “transversales”.
- `Clinical/Infrastructure/` catch-all (LIS + receta + HC).

## Consecuencias

- Localidad: el flow/agent/enum de lab se busca bajo `Laboratory/`.
- Otros BCs grandes pueden adoptar el mismo patrón módulo-primero cuando el layer-first deje de escalar.
- Relacionado: [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md), [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md), [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md), [domain-folder-grammar.md](./domain-folder-grammar.md), `Domain/Clinical/README.md`.
