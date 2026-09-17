# Bounded contexts, capas DDD y metadata

**Estado:** aceptado (migración de paths cerrada).

## Contexto

`components/Domain/` agrupaba el rubro sin capas claras; `Service/` mezclaba use cases, agents, connectors y hasta widgets. La metadata en `common/metadata/bioenlace/` mezclaba composición de motores (flows, prompts, routing) con políticas de negocio (agents knobs, capacity rules, agendas) y lookups.

## Decisión

1. **Bounded context (BC)** = carpeta de primer nivel bajo `components/Domain/` con lenguaje y dueño de modelo propios (`Clinical`, `Scheduling`, `Person`, `Organization`, `Terminology`, `Geo`, `Programs`, `Content`). `Platform/` es eje de **motores**; `Shared/` es eje de **infra/tipos transversales** — ninguno es BC bajo Domain. La carpeta residual `Integrations/` (solo README) **no** es BC (`ProductDomainCatalog` la excluye).
2. **Dentro de un BC grande (p. ej. Clinical):** eje **módulo de capacidad** primero; capas DDD **dentro** del módulo. Sin `Shared/` / `Enum` / `Service` catch-all en la raíz del BC. Ver [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md). `Infrastructure/External` del ACL clínico va **bajo el módulo dueño**, no en `Clinical/Infrastructure/`.
3. **YAML que permanece** (composición / copy / prompt / auth de motor / manifests UI) se **colocaliza** bajo la capa correspondiente del módulo o de Platform (p. ej. `<Modulo>/Application/Flows/intents`, `Presentation/`, `Platform/Assistant/…`).
4. **YAML que no permanece:** knobs de agents, contratos/catálogos de negocio, aliases de lookup → PHP Domain/Application o BD + seed.
5. **`Integrations` no es BC:** Anti-Corruption Layer en `Infrastructure/External` del módulo/BC dueño; UI Widget fuera de Domain (`Platform/Ui/Widgets/…`). Infra técnica compartida → [`Shared/Infrastructure`](../arquitectura/common-components.md). Ver [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md).
6. **Active Record** en `common/models/<BC>/` se trata como persistencia del BC (Infrastructure/Persistence ancla README en el módulo); sin exigir Entities/Aggregates puros en runtime hasta Fase C del plan de gramática.
7. **Discovery de intents:** `ProductMetadataPaths::colocatedIntentRoots()` incluye `Domain/<BC>/Application/Flows/intents` y `Domain/<BC>/<Modulo>/Application/Flows/intents` (+ Platform).
8. **Varios BCs:** Scheduling, Person, Organization, Terminology, etc. **no** se fusionan en Clinical (lenguaje y dueño de modelo distintos).

## Alternativas descartadas

- Un solo `Application/` global del monorepo (layer-first sin BC).
- Agrupadores `Core/` / `Support/` / `Shared/` **dentro** del slot de dominio o como basurero del BC (Shared **top-level** sí está permitido: [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md)).
- Sustituir YAML de flows por PHP o JSON sin ganancia.
- Domain model puro sin AR en el mismo programa.
- Mantener dual-root legacy + colocalizado de forma permanente.
- `Clinical/Infrastructure/` catch-all para ACL de varios módulos.

## Consecuencias

- `IntentSchemaPaths` / `ProductMetadataPaths` descubren intents en BC plano y en `<Modulo>/Application/Flows`.
- `AgentPolicyRegistry` + `*AgentPolicy` sustituyen YAML de agents (bajo el módulo dueño en Clinical).
- Catálogos de negocio en PHP `*/Domain/*Catalog`.
- Clinical: [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md).
- Shared top-level: [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md).
- Relacionado: [runtime-datos-vs-metadata.md](./runtime-datos-vs-metadata.md); [captura-clinica-contratos-yii-vs-yaml.md](./captura-clinica-contratos-yii-vs-yaml.md).
