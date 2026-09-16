# `Domain/` — negocio Bioenlace (salud / HIS)

Namespace base: `common\components\Domain\…`

Todo lo **específico del rubro actual**. Para otro producto, esta carpeta se reemplaza o se empaqueta aparte; los motores en `../Platform/` permanecen.

Las carpetas de primer nivel **son** el conjunto de dominios del producto (`ProductDomainCatalog`). No existe `Domain/Platform/`.

## Subcarpetas

| Carpeta | Contenido |
|---------|-----------|
| **`Clinical/`** | Módulos de capacidad (Encounter, Emergency, Lab, …) — ver [Clinical/README.md](./Clinical/README.md) |
| **`Scheduling/`** | Turnos, agenda, quirófano |
| **`Person/`** | Personas, registro, representación, ventanilla |
| **`Organization/`** | Efectores, PES, sesión operativa |
| **`Terminology/`** | SNOMED (`Terminology/Domain/*Catalog`) |
| **`Content/`** | Contenido institucional / novedades |
| **`Geo/`** | Maestros geo y recursos provinciales |
| **`Programs/`** | Programas de salud / SUMAR |
| **`Integrations/`** | Solo README de redirección (ACL → `*/Infrastructure/External/`) |

Flows: `Domain/<BC>/Application/Flows/intents` y/o `Domain/<BC>/<Modulo>/Application/Flows/intents`.  
ADR: [ddd-bounded-contexts-capas-y-metadata.md](../../../docs/decisions/ddd-bounded-contexts-capas-y-metadata.md), [clinical-modulos-capacidad.md](../../../docs/decisions/clinical-modulos-capacidad.md).

## Forma interna (gramática)

### BC grande (Clinical) — módulo primero

```text
Domain/Clinical/<Modulo>/
  Application/{Flows,Agent}/
  Domain/                    # enums, catalogs del módulo
  Service/ | Dto | …
  Infrastructure/            # si el módulo tiene ACL propio
```

Sin `Shared/`, sin `Enum/` / `Service/` / `Dto/` en la raíz del BC. Dueño explícito; dependencias hacia el núcleo (`Encounter`, `CarePlan`).

### BC más chico / en migración

```text
Domain/<BC>/
  Application/ | Domain/ | Infrastructure/External/
  <Área>/Service/
  Assistant/ | Home/ | DataAccess/
```

### Sufijos de clase

| Sufijo | Uso |
|--------|-----|
| `*Service` | Caso de uso / dominio |
| `*Policy` / `*Access` | Autorización de recurso |
| `*Catalog` / `*CatalogService` | Vocabulario / knobs |
| `*Agent` | Job / batch / side-effects |
| `*FlowDraftHydrator` | Plugin flow asistente |
| `*HintCandidateProvider` | Hints asistente |
| `*SectionProvider` | Panel home |
| `*Presenter` / `*Presentation` | Presentación sin HTTP |
| `*Connector` / `*Mapper` / `*Registry` | ACL External |

## Cableado con motores

Handlers dominio → motor: **`common/config/product-registries.php`**, no dentro de `Platform/Assistant/`.
