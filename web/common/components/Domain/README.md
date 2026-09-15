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
| **`Terminology/`** | SNOMED, codificación clínica (`SnomedCategoryCatalog`, `SnomedSearchProfileCatalog`) |
| **`Content/`** | Contenido institucional / novedades |
| **`Geo/`** | Maestros geo y recursos provinciales |
| **`Programs/`** | Programas de salud / SUMAR |
| **`Integrations/`** | Solo README de redirección (ACL → `*/Infrastructure/External/`) |

Catálogos de negocio: `Domain/<BC>/Domain/*Catalog` (PHP). Flows: `Application/Flows/intents/`. SNOMED knobs: `Terminology/Domain/SnomedTerminologyCatalog`.

Árbol espejo en otras capas: [arbol-espejo-dominios.md](../../../docs/arquitectura/arbol-espejo-dominios.md). ADR DDD: [ddd-bounded-contexts-capas-y-metadata.md](../../../docs/decisions/ddd-bounded-contexts-capas-y-metadata.md).

## Forma interna (gramática)

Orden fijo: **dominio → capa DDD / subdominio → rol técnico**.

```text
Domain/<BC>/
  Application/               # Flows, Agent, use cases de orquestación
  Domain/                    # *Catalog, *Policy, *Enum (sin I/O externo)
  Infrastructure/External/<Sistema>/   # ACL: Contract, Connector, Mapper, …
  Presentation/              # copy / presenters del BC (si aplica)
  <Subdominio>/Service/      # área real (Emergency, PedidoAtencion, …)
  Service/                   # legacy / default del BC (en migración)
  Assistant/ | Home/ | DataAccess/
```

Adapters externos viven en el **BC dueño** (`Person` ← MPI/Didit; `Clinical` ← lab/receta/HC; `Scheduling` ← FHIR agenda). Ver [Integrations/README.md](./Integrations/README.md).

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
