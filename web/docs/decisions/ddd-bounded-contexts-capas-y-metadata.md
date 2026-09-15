# Bounded contexts, capas DDD y metadata

**Estado:** aceptado (migración de paths cerrada).

## Contexto

`components/Domain/` agrupaba el rubro sin capas claras; `Service/` mezclaba use cases, agents, connectors y hasta widgets. La metadata en `common/metadata/bioenlace/` mezclaba composición de motores (flows, prompts, routing) con políticas de negocio (agents knobs, capacity rules, agendas) y lookups.

## Decisión

1. **Bounded context (BC)** = carpeta de primer nivel bajo `components/Domain/` con lenguaje y dueño de modelo propios (`Clinical`, `Scheduling`, `Person`, `Organization`, `Terminology`, `Geo`, `Programs`, `Content`). `Platform/` es eje paralelo (motores), no un BC bajo Domain. La carpeta residual `Integrations/` (solo README) **no** es BC (`ProductDomainCatalog` la excluye).
2. **Capas dentro del BC:** `Application` / `Domain` / `Infrastructure` / `Presentation`, más adapters `Assistant` | `Home` | `DataAccess` hacia motores. Sufijos tipados (`*Service`, `*Agent`, `*Policy`, `*Connector`, …).
3. **YAML que permanece** (composición / copy / prompt / auth de motor / manifests UI) se **colocaliza** bajo la capa correspondiente del BC o de Platform (p. ej. `Application/Flows/intents`, `Presentation/`, `Platform/Assistant/…`).
4. **YAML que no permanece:** knobs de agents, contratos/catálogos de negocio, aliases de lookup → PHP Domain/Application o BD + seed.
5. **`Integrations` no es BC:** Anti-Corruption Layer en `Infrastructure/External` del BC dueño; UI Widget fuera de Domain (`Platform/Ui/Widgets/…`).
6. **Active Record** en `common/models/<BC>/` se trata como persistencia del BC (Infrastructure), sin exigir Entities/Aggregates puros en esta etapa.
7. **Una sola raíz de discovery** para intents: `ProductMetadataPaths::colocatedIntentRoots()` / `IntentSchemaPaths` (sin fallback a `metadata/bioenlace`).

## Alternativas descartadas

- Un solo `Application/` global del monorepo (layer-first sin BC).
- Agrupadores `Core/` / `Support/` en el slot de dominio del espejo.
- Sustituir YAML de flows por PHP o JSON sin ganancia.
- Domain model puro sin AR en el mismo programa.
- Mantener dual-root legacy + colocalizado de forma permanente.

## Consecuencias

- `IntentSchemaPaths` / `ProductMetadataPaths` solo conocen paths colocalizados.
- `AgentPolicyRegistry` + `*AgentPolicy` sustituyen YAML de agents.
- `Domain/<BC>/Domain/*Catalog` sustituye knobs YAML de negocio.
- Relacionado: [runtime-datos-vs-metadata.md](./runtime-datos-vs-metadata.md) (maestros → BD); [captura-clinica-contratos-yii-vs-yaml.md](./captura-clinica-contratos-yii-vs-yaml.md) (integridad no va en YAML).
