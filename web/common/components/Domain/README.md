# `Domain/` — negocio Bioenlace (salud / HIS)

Namespace base: `common\components\Domain\…`

Todo lo **específico del rubro actual**. Para otro producto, esta carpeta se reemplaza o se empaqueta aparte; los motores en `../Platform/` y la infra en `../Shared/` permanecen.

Las carpetas de primer nivel **son** el conjunto de dominios del producto (`ProductDomainCatalog`). No existe `Domain/Platform/` ni `Domain/Shared/`.

## Subcarpetas (BCs)

| Carpeta | Contenido |
|---------|-----------|
| **`Clinical/`** | Módulos de capacidad — [Clinical/README.md](./Clinical/README.md) |
| **`Scheduling/`** | Turnos, agenda, quirófano |
| **`Person/`** | Personas, registro, representación, ventanilla |
| **`Organization/`** | Efectores, PES, sesión operativa |
| **`Terminology/`** | SNOMED |
| **`Content/`** | Contenido institucional |
| **`Geo/`** | Maestros geo |
| **`Programs/`** | Programas / SUMAR |
| **`Integrations/`** | Solo README (ACL → `*/Infrastructure/External/`) |

ADR: [ddd-norte-modelo-rico.md](../../../docs/decisions/ddd-norte-modelo-rico.md) (**cero ambigüedad de ejes**), [domain-folder-grammar.md](../../../docs/decisions/domain-folder-grammar.md), [clinical-modulos-capacidad.md](../../../docs/decisions/clinical-modulos-capacidad.md), [ddd-bounded-contexts-capas-y-metadata.md](../../../docs/decisions/ddd-bounded-contexts-capas-y-metadata.md).

## Forma interna

```text
Domain/Clinical/<Modulo>/
  Application/
    UseCase/ | Presentation/ | Authorization/ | Flows/ | Agents/
    *.php                    # services Application; dominio en el nombre de clase
  Domain/
    Model/ | Catalog/ | Policy/ | Port/ | RowContract/ | …
  Infrastructure/
    External/ | Persistence/ | <Adapter>/…
```

**`Application/*` = solo rol CA (técnico).** Prohibido: `Checkpoint/`, `Extraction/`, `Support/`, etc.  
Dominio de producto = nombre de **módulo** + nombre de **clase** + carpetas bajo `Domain/`.

### Sufijos

| Sufijo | Carpeta |
|--------|---------|
| Interactor | `Application/UseCase/` |
| `*Presenter` | `Application/Presentation/` |
| `*Service` Application | `Application/` (raíz) |
| `*Access` | `Application/Authorization/` |
| Aggregate | `Domain/Model/` |
| `*Catalog` / policies | `Domain/…` |
| `*RowContract` | `Domain/RowContract/` |
| ACL | `Infrastructure/External/` |

## Cableado con motores

Handlers dominio → motor: **`common/config/product-registries.php`**.
