# Gramática de carpetas y sufijos en Domain/

**Estado:** aceptado.

## Contexto

Tras módulo-primero en Clinical y Shared top-level, la forma **dentro** de módulos y BCs seguía heterogénea: PHP en raíz de módulo, `Support/`/`Mapper/`/`Batch/` ad-hoc, Presenter/Hydrator/Catalog fuera de la carpeta del sufijo.

## Decisión

### Clinical — módulo de capacidad

```text
Domain/Clinical/<Modulo>/
  Application/{Flows,Agent}/
  Domain/                         # *Catalog, enums, policies tipadas
  Service/                        # *Service, *Access; Authorization/ opcional
  Dto/                            # opcional
  Presentation/                   # *Presenter / *PresentationService
  Infrastructure/External/…       # Connector|Contract|Dto|Exception|Mapper|Registry
```

Plugins del BC solo en raíz Clinical: `Assistant/`, `Home/`, `DataAccess/`.

**Prohibido:** PHP de negocio en raíz del módulo o del BC; `Support/`; `Mapper/` fuera de `Infrastructure/External/`; `Batch/` en L1 (usar `Infrastructure/` o `Application/Agent`); `Assistant/` bajo un módulo; `Clinical/Infrastructure/` en raíz del BC.

### BC chico / mediano

```text
Domain/<BC>/
  Application/{Flows,Agent}/
  Domain/
  Service/                        # o Service/<Área>/ si crece
  Presentation/
  Infrastructure/External/…
  Assistant/ | Home/ | DataAccess/
```

Áreas de lenguaje (`Ventanilla`, `Quirofano`, `Representation`) permitidas si tienen capas internas.

### Sufijos → carpeta

| Sufijo | Carpeta |
|--------|---------|
| `*Service`, `*Access` | `Service/` |
| `*Catalog` | `Domain/` |
| `*CatalogService` | `Service/` |
| `*Agent`, `*AgentPolicy` | `Application/Agent/` |
| `*Presenter`, `*PresentationService` | `Presentation/` |
| `*FlowDraftHydrator` | `Assistant/` |
| `*Connector` / `*Mapper` / `*Registry` (ACL) | `Infrastructure/External/…` |
| `*SectionProvider` | `Home/Sections/` |

Lenguaje ubicuo en español en **nombres de clase** (`Turno*`, `Guardia*`, `PedidoAtencion`) se conserva. Carpetas técnicas en inglés.

### Deferido (oleadas aparte)

- Partir `Scheduling/Service/` (~100 PHP) en `Service/<Área>/` o módulo-primero.
- Fusionar `Specialty/Inpatient` vs `Inpatient/`.
- Eliminar o reubicar `Encounter/Legacy/` → `Encounter/Service/Legacy/` cuando se retire el puente legacy.
- Interfaces de catálogo (`*CatalogInterface`) pueden vivir en `Domain/` en una pasada posterior; hoy varias siguen en `Service/`.

## Relacionado

- [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md)
- [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md)
- [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md)
- `Domain/README.md`, `Clinical/README.md`
