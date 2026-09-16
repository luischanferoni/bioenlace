# `Domain/` — negocio Bioenlace (salud / HIS)

Namespace base: `common\components\Domain\…`

Todo lo **específico del rubro actual**. Para otro producto, esta carpeta se reemplaza o se empaqueta aparte; los motores en `../Platform/` y la infra en `../Shared/` permanecen.

Las carpetas de primer nivel **son** el conjunto de dominios del producto (`ProductDomainCatalog`). No existe `Domain/Platform/` ni `Domain/Shared/`.

## Subcarpetas (BCs)

| Carpeta | Contenido |
|---------|-----------|
| **`Clinical/`** | Módulos de capacidad (Encounter, Emergency, Lab, …) — ver [Clinical/README.md](./Clinical/README.md) |
| **`Scheduling/`** | Turnos, agenda, quirófano (BC aparte; no es módulo de Clinical) |
| **`Person/`** | Personas, registro, representación, ventanilla |
| **`Organization/`** | Efectores, PES, sesión operativa |
| **`Terminology/`** | SNOMED (`Terminology/Domain/*Catalog`) — BC catálogo, no módulo Clinical |
| **`Content/`** | Contenido institucional / novedades |
| **`Geo/`** | Maestros geo y recursos provinciales |
| **`Programs/`** | Programas de salud / SUMAR |
| **`Integrations/`** | Solo README de redirección (ACL → `*/Infrastructure/External/`) |

Flows: `Domain/<BC>/Application/Flows/intents` y/o `Domain/<BC>/<Modulo>/Application/Flows/intents`.  
ADR: [ddd-bounded-contexts-capas-y-metadata.md](../../../docs/decisions/ddd-bounded-contexts-capas-y-metadata.md), [clinical-modulos-capacidad.md](../../../docs/decisions/clinical-modulos-capacidad.md), [shared-top-level-infrastructure.md](../../../docs/decisions/shared-top-level-infrastructure.md), [domain-folder-grammar.md](../../../docs/decisions/domain-folder-grammar.md).

## Forma interna (gramática)

Fuente de verdad: [domain-folder-grammar.md](../../../docs/decisions/domain-folder-grammar.md).

### BC grande (Clinical) — módulo primero

```text
Domain/Clinical/<Modulo>/
  Application/{Flows,Agent}/
  Domain/                    # *Catalog, enums
  Service/                   # *Service; Authorization/ opcional
  Dto/ | Presentation/       # opcional
  Infrastructure/External/   # ACL (Mapper/Connector/…)
```

Sin `Shared/`, sin `Enum/` / `Service/` / `Dto/` / `Infrastructure/` / PHP suelto en la **raíz** del BC o del módulo. Sin `Support/` ni `Mapper/` fuera de External.

### BC más chico / en migración

```text
Domain/<BC>/
  Application/ | Domain/ | Service/ | Presentation/ | Infrastructure/External/
  Assistant/ | Home/ | DataAccess/
```

### Sufijos de clase → carpeta

| Sufijo | Carpeta |
|--------|---------|
| `*Service` / `*Access` | `Service/` |
| `*Catalog` | `Domain/` |
| `*CatalogService` | `Service/` |
| `*Agent` / `*AgentPolicy` | `Application/Agent/` |
| `*Presenter` / `*PresentationService` | `Presentation/` |
| `*FlowDraftHydrator` | `Assistant/` |
| `*HintCandidateProvider` | `Assistant/` (hints) |
| `*Connector` / `*Mapper` / `*Registry` (ACL) | `Infrastructure/External/` |
| `*SectionProvider` | `Home/Sections/` |

## Cableado con motores

Handlers dominio → motor: **`common/config/product-registries.php`**, no dentro de `Platform/Assistant/`.
