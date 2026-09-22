# `Domain/` — negocio Bioenlace (salud / HIS)

Namespace base: `common\components\Domain\…`

Todo lo **específico del rubro actual**. Para otro producto, esta carpeta se reemplaza o se empaqueta aparte; los motores en `../Platform/` y la infra en `../Shared/` permanecen.

Las carpetas de primer nivel **son** el conjunto de dominios del producto (`ProductDomainCatalog`). No existe `Domain/Platform/` ni `Domain/Shared/`.

## Subcarpetas (BCs)

| Carpeta | Forma | Contenido |
|---------|-------|-----------|
| **`Clinical/`** | Módulo-primero | Encounter, Emergency, Capture, … |
| **`Organization/`** | Módulo-primero | Efector, Servicio, Pes, SesionOperativa |
| **`Scheduling/`** | Módulo-primero | Agenda, BehaviorProfile, Quirofano, Home |
| **`Person/`** | Módulo-primero | Identidad, Representation, FrontDesk |
| **`Terminology/`** | BC compacto | SNOMED |
| **`Content/`** | BC compacto | Contenido institucional |
| **`Geo/`** | BC compacto | Maestros geo |
| **`Programs/`** | BC compacto | Programas / SUMAR (esqueleto) |
| **`Integrations/`** | Solo README | ACL → `*/Infrastructure/External/` |

ADRs: [ddd-norte-modelo-rico.md](../../../docs/decisions/ddd-norte-modelo-rico.md), [domain-folder-grammar.md](../../../docs/decisions/domain-folder-grammar.md), [clinical-modulos-capacidad.md](../../../docs/decisions/clinical-modulos-capacidad.md), [ddd-modulo-primero-vs-bc-compacto.md](../../../docs/decisions/ddd-modulo-primero-vs-bc-compacto.md), [ddd-bounded-contexts-capas-y-metadata.md](../../../docs/decisions/ddd-bounded-contexts-capas-y-metadata.md).

## Forma interna

### Módulo-primero (Clinical, Organization, Scheduling, Person)

```text
Domain/<BC>/<Modulo>/
  Application/
    UseCase/ | Presentation/ | Service/ | Authorization/ | Flows/ | Agents/ | Seed/
  Domain/
    Model/ | Catalog/ | Policy/ | Port/ | RowContract/ | …
  Infrastructure/
    External/ | Persistence/ | <Adapter>/…
```

Plugins del BC (no módulos de capacidad): `Assistant/`, `Home/`, `DataAccess/` en la raíz del BC.

### BC compacto (Geo, Content, Terminology, …)

Layer-first en la raíz del BC **mientras** hay una sola capacidad:

```text
Domain/<BC>/
  Application/{UseCase,Service,Seed,…}
  Domain/{Model,Catalog,…}
  Infrastructure/{External,Persistence,…}
```

Si aparece una segunda capacidad → promover a módulo-primero (misma gramática).

**`Application/*` = solo rol CA.** Prohibido: carpetas de capacidad (`Efectores/`, `Dto/`, `Reminder/`, …) y PHP suelto en la raíz de `Application/`.

### Sufijos (catálogo cerrado)

| Sufijo | Carpeta |
|--------|---------|
| Verb phrase | `Application/UseCase/` |
| `*Presenter` | `Application/Presentation/` |
| `*Service` / `*Resolver` / `*Applier` | `Application/Service/` |
| `*Access` | `Application/Authorization/` |
| `*Agent` / `*AgentPolicy` | `Application/Agents/` |
| Aggregate / VO | `Domain/Model/` |
| `*Catalog` | `Domain/Catalog/` |
| `*Policy` | `Domain/Policy/` |
| `*Repository` / `*Port` / `*Registry` | `Domain/Port/` |
| `*RowContract` | `Domain/RowContract/` |
| ACL `*Connector` / `*Mapper` | `Infrastructure/External/…` |

## Cableado con motores

Handlers dominio → motor: **`common/config/product-registries.php`**.
