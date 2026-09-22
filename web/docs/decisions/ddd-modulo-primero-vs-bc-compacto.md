# Módulo-primero vs BC compacto

**Estado:** aceptado.  
**Extiende:** [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md), [domain-folder-grammar.md](./domain-folder-grammar.md).

## Decisión

1. **Módulo-primero** aplica a BCs con varias capacidades de producto:
   - `Clinical/` (ya)
   - `Organization/` (`Efector/`, `Servicio/`, `Pes/`, `SesionOperativa/`)
   - `Scheduling/` (`Agenda/`, `BehaviorProfile/`, `Quirofano/`, `Home/`)
   - `Person/` (`Identidad/`, `Representation/`, `FrontDesk/`)
2. **BC compacto** (layer-first en la raíz) queda permitido mientras el BC sea mono-capacidad: `Geo/`, `Content/`, `Terminology/`, `Programs/`.
3. En **ambos** casos, `Application/*` solo admite roles CA (`UseCase/`, `Service/`, `Presentation/`, `Authorization/`, `Flows/`, `Agents/`, `Seed/`). Sin carpetas de capacidad ni PHP suelto en la raíz de `Application/`.
4. Si un BC compacto gana una segunda capacidad clara → promover a módulo-primero (sin inventar módulos “por simetría”).
5. Plugins de BC (`Assistant/`, `Home/`, `DataAccess/`) siguen en la raíz del BC.

## Consecuencias

- Guardrail: `BoundedContextLayerShapeTest` (Clinical, Organization, Scheduling, Person, BCs compactos).
- Discovery de intents: `Domain/<BC>/[Modulo/]Application/Flows/intents` (`ProductMetadataPaths`).
- Documentación viva: `Domain/README.md`, [common-components.md](../arquitectura/common-components.md).
