# `common/components` — organización y responsabilidades

**Leer este documento antes de crear, mover o refactorizar código en `web/common/components/`.**

Código reutilizable por API v1, consola, jobs y (legacy) frontend Yii. La regla de oro: **separar motores (`Platform/`), shared técnico (`Shared/`) y rubro (`Domain/`)**, y declarar el producto en metadata + registries.

## Tres capas top-level

| Capa | Ruta | Namespace | Contenido |
|------|------|-----------|-----------|
| **Plataforma / motores** | `components/Platform/` | `common\components\Platform\…` | IA, asistente, DataAccess, permisos genéricos, UI JSON |
| **Shared** | `components/Shared/` | `common\components\Shared\…` | Infra técnica y tipos transversales |
| **Rubro Bioenlace** | `components/Domain/` | `common\components\Domain\…` | Clínico, turnos, personas, organización, terminología, … |

**Metadata del producto:** YAML colocalizado en `Domain/<BC>/[Modulo/]Application/Flows/` y `Platform/{Assistant,Ui,Core/Permission,Ai}/…`. Knobs de negocio en PHP (`*Catalog`, `*AgentPolicy`). Ver [metadata/bioenlace/README.md](../../common/metadata/bioenlace/README.md).

**Cableado dominio → motor:** `common/config/product-registries.php` (`productRegistries` en `params.php`).

Para otro rubro: nuevo `Domain/`, metadata y registries; **`Platform/`** y **`Shared/`** se mantienen.

## `Platform/` — motores (agnósticos)

| Carpeta | Contenido |
|---------|-----------|
| **`Platform/Assistant/`** | IntentEngine, SubIntentEngine, Chat — ver [Assistant/README.md](../../common/components/Platform/Assistant/README.md) |
| **`Platform/Core/`** | DataAccess, permisos, push, `Core/Product/` |
| **`Platform/Ui/`** | Pantallas JSON, panel home (motor), grid |
| **`Platform/Ai/`** | Proveedores IA, STT genérico, embeddings |

**Prohibido** en `Platform/`: reglas de negocio clínico, listas de intents por rubro, `if (intentId === …)`.

## `Shared/` — transversal técnico

| Carpeta | Contenido |
|---------|-----------|
| **`Shared/Infrastructure/`** | Log resiliente, helpers migración, deduplicación requests, bases HTTP/messaging |
| **`Shared/Domain/`** | Tipos transversales mínimos (sin negocio clínico) |

ACL de sistemas de negocio **no** van aquí. ADR: [shared-top-level-infrastructure.md](../decisions/shared-top-level-infrastructure.md).

## `Domain/` — negocio salud

| Carpeta | Contenido |
|---------|-----------|
| **`Domain/Clinical/`** | Módulos: Encounter, Emergency, Inpatient, Laboratory, Prescription, CarePlan, Capture, HistoryExchange, … |
| **`Domain/Scheduling/`** | Turnos, agenda, quirófano (BC aparte) |
| **`Domain/Person/`** | Personas, registro |
| **`Domain/Organization/`** | Efectores, PES, sesión operativa |
| **`Domain/<BC|Modulo>/Infrastructure/External/`** | ACL sistemas externos (MPI, LIS, receta, HC FHIR, agenda FHIR) |
| **`Domain/Terminology/`** | SNOMED (BC catálogo) |
| **`Domain/Content/`** | Contenido institucional (`InfoContent`, novedades) |
| **`Domain/Geo/`** | Maestros geo (provincias, recursos provinciales) |
| **`Domain/Programs/`** | Programas de salud / SUMAR |

**No crear** carpetas clínicas sueltas fuera de `Domain/Clinical/` (`Emergency/`, `Inpatient/` van ahí). **No** existe `Domain/Platform/` ni `Domain/Shared/`. **No** `Clinical/Infrastructure/` en la raíz del BC.

## Árbol espejo (otras capas)

La misma palabra de dominio se sigue en models, controllers API, `views/json`, metadata y tests. Slot transversal: `platform` o `shared`. Ver [arbol-espejo-dominios.md](./arbol-espejo-dominios.md). Fuente del conjunto de BCs: `ProductDomainCatalog` ← carpetas en `Domain/`.

## Patrones dentro de un dominio

**Clinical (BC grande):** módulo de capacidad primero — [clinical-modulos-capacidad.md](../decisions/clinical-modulos-capacidad.md). Gramática de sufijos: [domain-folder-grammar.md](../decisions/domain-folder-grammar.md).

```text
Domain/Clinical/<Modulo>/Application|Domain|Infrastructure/…
```

**Otros BCs / migración:**

```text
Domain/<Dominio>/
  Application/ | Domain/ | Infrastructure/External/
  Assistant/ | Home/ | DataAccess/
```

- **ACL externos:** `Domain/<BC>/<Modulo?>/Infrastructure/External/<Sistema>/`.
- **Sufijos:** `*Service`→`Application/`, `*Access`→`Application/Authorization/`, `*Catalog`→`Domain/`, `*Presenter`→`Application/Presentation/`, `*Agent*`→`Application/Agents/`, `*FlowDraftHydrator`→`Assistant/`. Sin `Service/` L1.
- Plugins: `product-registries.php` + clases en `Domain/…`.
- Detalle: [Domain/README.md](../../common/components/Domain/README.md).
- ADR DDD: [ddd-bounded-contexts-capas-y-metadata.md](../decisions/ddd-bounded-contexts-capas-y-metadata.md).
- Gramática: [domain-folder-grammar.md](../decisions/domain-folder-grammar.md).

## Motores vs metadata vs negocio

| Capa | Ubicación | Responsabilidad |
|------|-----------|-----------------|
| **Motores** | `Platform/Assistant/…`, `Platform/Core/DataAccess`, `Platform/Core/Product/` | Interpretar manifiestos; sin reglas por rubro en PHP |
| **Shared** | `Shared/Infrastructure`, `Shared/Domain` | Infra y tipos transversales |
| **Metadata producto** | Colocalizada en Platform/BC/módulo (`Application/Flows`, prompts, ui-text); knobs en PHP `*Catalog` / `*AgentPolicy` | Composición + políticas tipadas |
| **Plugins dominio** | `product-registries.php` + clases en `Domain/` | Catálogos UI, scope, políticas, panel home |
| **Negocio** | `Domain/Clinical/`, `Domain/Scheduling/`, … | Persistencia, reglas, autorización de recurso |

## Dónde ubicar código nuevo

| Necesidad | Ubicación |
|-----------|-----------|
| Guardia, triage | `Domain/Clinical/Emergency/` |
| Internación | `Domain/Clinical/Inpatient/` |
| Encounter / condiciones / journey | `Domain/Clinical/Encounter/` |
| Care plan / órdenes | `Domain/Clinical/CarePlan/` |
| Captura / documentación / texto clínico | `Domain/Clinical/Capture/` |
| Laboratorio + LIS | `Domain/Clinical/Laboratory/` (+ `Infrastructure/External`) |
| Receta + repositorio digital | `Domain/Clinical/Prescription/` (+ `Infrastructure/External`) |
| HC nacional | `Domain/Clinical/HistoryExchange/` (+ `Infrastructure/External`) |
| Pedido de atención | `Domain/Clinical/PedidoAtencion/` |
| Persona, registro | `Domain/Person/` |
| Turno, agenda | `Domain/Scheduling/` |
| Helper HTTP / log / migración | `Shared/Infrastructure/` |
| Intent Clinical | `Domain/Clinical/<Modulo>/Application/Flows/intents/` |
| Intent otros BC | `Domain/<BC>/Application/Flows/intents/` |

## Referencias

- [README en código](../../common/components/README.md)
- [Platform/README.md](../../common/components/Platform/README.md)
- [Shared/README.md](../../common/components/Shared/README.md)
- [Domain/README.md](../../common/components/Domain/README.md)
- [Asistente — motores](./asistente-motores.md)
