# `Domain/` — negocio Bioenlace (salud / HIS)

Namespace base: `common\components\Domain\…`

Todo lo **específico del rubro actual**. Para otro producto, esta carpeta se reemplaza o se empaqueta aparte; los motores en `../Platform/` permanecen.

Las carpetas de primer nivel **son** el conjunto de dominios del producto (`ProductDomainCatalog`). No existe `Domain/Platform/`.

## Subcarpetas

| Carpeta | Contenido |
|---------|-----------|
| **`Clinical/`** | Encounters, guardia, internación, prescripción, lab, texto clínico (`Text/`), legacy consulta |
| **`Scheduling/`** | Turnos, agenda, quirófano |
| **`Person/`** | Personas, registro, representación (`Representation/`), ventanilla (`Ventanilla/Service/`) |
| **`Organization/`** | Efectores, PES, sesión operativa |
| **`Integrations/`** | SISSE, receta digital, **export HC FHIR**, MPI, laboratorio FHIR, identidad |
| **`Terminology/`** | SNOMED, codificación clínica (`SnomedCategoryCatalog`, `SnomedSearchProfileCatalog`) |
| **`Content/`** | Contenido institucional / novedades |
| **`Geo/`** | Maestros geo y recursos provinciales |
| **`Programs/`** | Programas de salud / SUMAR |

Metadata SNOMED: `common/metadata/bioenlace/terminology/snomed-terminology.yaml` (ECL canónicos + codificación + búsqueda).

Árbol espejo en otras capas: [arbol-espejo-dominios.md](../../../docs/arquitectura/arbol-espejo-dominios.md).

## Forma interna (gramática)

Orden fijo: **dominio → subdominio de negocio (opcional) → rol técnico → capacidad (opcional)**.

```text
Domain/<Dominio>/
  <Subdominio>/              # área real (Emergency, PedidoAtencion, Representation…)
    Service/
    Enum/ | Dto/             # opcionales
  Service/                   # default del dominio
    <Capacidad>/             # solo si el tema crece (≥ ~4–5 clases)
  Assistant/ | Home/ | DataAccess/ | Presentation/
  metadata/                  # knobs YAML del dominio (no maestros de request)
```

**Integrations** (excepción): el 2.º nivel es el **sistema externo**, no un subdominio clínico:

```text
Domain/Integrations/<Sistema>/{Contract,Connector,Mapper,Service,Dto,Exception}/
```

La orquestación de negocio que *usa* el connector vive en `Clinical/…`, `Scheduling/…`, etc. **No** crear `common/components/Integrations/` hermana de `Domain/`.

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
| `*SeedService` | Seeds consola |
| `*Mapper` / `*Connector` / `*Registry` | Integrations |

Código nuevo solo en esta forma. Existente: migrar al tocar el área.

## Cableado con motores

Los handlers que conectan dominio con motores genéricos (hydrators, scope checkers, catálogos UI, secciones del panel) se registran en **`common/config/product-registries.php`**, no dentro de `Platform/Assistant/`.

## Clinical — subdominios

| Ruta | Uso |
|------|-----|
| `Clinical/Service/` | Encounter, care plans, service requests |
| `Clinical/PedidoAtencion/Service/` | Pedido de atención (línea × acto, coding, capacity) |
| `Clinical/Emergency/` | Guardia, triage |
| `Clinical/Inpatient/` | Mapa camas, ingreso/alta |
| `Clinical/Text/` | Procesador texto clínico, SymSpell médico |
| `Clinical/Legacy/` | Puente consulta legacy |
| `Clinical/HistoryExchange/` | Cola export FHIR HC → nacional |

Ver [Clinical/README.md](./Clinical/README.md).
