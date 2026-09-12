# Design — Forma interna de Domain

## Dos capas top-level (invariante)

```text
common/components/
  Platform/     # motores
  Domain/       # rubro (incluye Integrations, Geo, Terminology, …)
```

No hay tercera raíz `Integrations/` hermana de `Domain/`.

## Orden de carpetas

1. **Dominio** (`Clinical`, `Scheduling`, `Person`, `Organization`, `Integrations`, …) — fuente: `ProductDomainCatalog`.
2. **Subdominio de negocio** solo si tiene ciclo de vida / enums propios (ej. `Emergency`, `Inpatient`, `Representation`, `PedidoAtencion`).
3. **Rol técnico**: `Service`, `Assistant`, `Home`, `DataAccess`, `Presentation`, `Enum`, `Dto`, `metadata`.
4. **Capacidad** bajo `Service/` si el listado del directorio deja de ser legible (`Quirofano/`, `ProfesionalEfectorServicio/`, `BehaviorProfile/`).

## Integrations (excepción documentada)

```text
Domain/Integrations/<Sistema>/
  Contract/ | Connector/ | Mapper/ | Service/ | Dto/ | Exception/
```

El segundo nivel es el **sistema externo**, no un subdominio clínico. La orquestación de negocio sigue en `Clinical/…`, `Scheduling/…`, etc.

## Sufijos de clase

| Sufijo | Uso |
|--------|-----|
| `*Service` | Caso de uso / dominio |
| `*Policy` / `*Access` | Autorización de recurso |
| `*Catalog` / `*CatalogService` | Vocabulario / knobs YAML |
| `*Agent` | Job / side-effects / batch |
| `*FlowDraftHydrator` | Plugin flow asistente |
| `*HintCandidateProvider` | Hints asistente |
| `*SectionProvider` | Panel home |
| `*Presenter` / `*Presentation` | Presentación sin HTTP |
| `*SeedService` | Seeds consola |
| `*Mapper` / `*Connector` / `*Registry` | Integrations |

## Política de migración

- Código **nuevo**: solo esta forma.
- Código **existente**: migrar al tocar el área (o en fases de este plan si el desvío es claro y acotado).
- Sin capas `@deprecated` / alias de namespace en la ruta vieja.
