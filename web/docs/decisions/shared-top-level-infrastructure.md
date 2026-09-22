# Shared top-level + Infrastructure por módulo

**Estado:** aceptado.

## Contexto

`Clinical/Infrastructure/External` agrupaba ACL de laboratorio, receta e HC como si fuera un módulo de capacidad: era un Shared disfrazado dentro del BC. A la vez hace falta un lugar explícito para infra técnica transversal (HTTP base, logging resiliente, helpers de migración) sin mezclarla con motores (`Platform/`) ni con BCs de negocio.

## Decisión

1. **`components/Shared/`** existe a la **misma altura** que `Domain/` y `Platform/`.
   - `Shared/Infrastructure/`: conexiones y utilidades técnicas compartidas.
   - `Shared/Domain/`: tipos transversales mínimos (sin reglas clínicas ni catálogos SNOMED).
2. **`Platform/`** sigue siendo solo **motores** (Assistant, Ai, Ui, Core). Lo que vivía en `Platform/Infra/` pasa a `Shared/Infrastructure/`.
3. **ACL de sistemas de negocio** (LIS, receta, HC, MPI, agenda FHIR) viven en **`Infrastructure/External` del módulo o BC dueño**, no en Shared:
   - `Clinical/Laboratory|Prescription|HistoryExchange/Infrastructure/External/`
   - `Person/Identity/Infrastructure/External/`, `Scheduling/Agenda/Infrastructure/External/NisFhir/`
4. **Prohibido** `Clinical/Infrastructure/` en la raíz del BC clínico.
5. **Prohibido** `Shared/` *dentro* de un BC (catch-all de enums/services). Shared solo top-level.
6. **Varios BCs** bajo `Domain/` (`Clinical`, `Scheduling`, `Person`, `Organization`, `Terminology`, `Geo`, `Content`, `Programs`). Scheduling y Terminology **no** son módulos de Clinical.
7. `ProductDomainCatalog` trata `shared` como slot transversal (como `platform`); no es dominio de producto. No debe existir `Domain/Shared/`.

## Alternativas descartadas

- Dejar ACL clínicos en `Clinical/Infrastructure` (Shared del BC).
- Meter Shared solo dentro de Clinical.
- Fusionar Scheduling/Terminology bajo Clinical.
- Absorber Shared dentro de Platform (mezcla motores con infra técnica genérica).

## Consecuencias

- Localidad: el conector LIS se busca bajo `Laboratory/`.
- Infra técnica reutilizable sin contaminar Platform ni Domain.
- Relacionado: [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md), [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md).
