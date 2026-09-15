# Bounded contexts, capas DDD y metadata

**Estado:** en implementación (borrador operativo). Al cerrar la migración, actualizar este ADR y retirar dual-root.

## Contexto

`components/Domain/` agrupaba el rubro sin capas claras; `Service/` mezclaba use cases, agents, connectors y hasta widgets. La metadata en `common/metadata/bioenlace/` mezclaba composición de motores (flows, prompts, routing) con políticas de negocio (agents knobs, capacity rules, agendas) y lookups.

## Decisión

1. **Bounded context (BC)** = carpeta de primer nivel bajo `components/Domain/` con lenguaje y dueño de modelo propios (`Clinical`, `Scheduling`, `Person`, `Organization`, `Terminology`, `Geo`, `Programs`, `Content`). `Platform/` es eje paralelo (motores), no un BC bajo Domain.
2. **Capas dentro del BC:** `Application` / `Domain` / `Infrastructure` / `Presentation`, más adapters `Assistant` | `Home` | `DataAccess` hacia motores. Sufijos tipados (`*Service`, `*Agent`, `*Policy`, `*Connector`, …).
3. **YAML que permanece** (composición / copy / prompt / auth de motor / manifests UI) se **colocaliza** bajo la capa correspondiente del BC o de Platform (p. ej. `Application/Flows/intents`, `Presentation/`, `Platform/Assistant/…`).
4. **YAML que no permanece:** knobs de agents, contratos/catálogos de negocio, aliases de lookup → PHP Domain/Application o BD + seed.
5. **`Integrations` no es BC:** Anti-Corruption Layer en `Infrastructure/External` del BC dueño; UI Widget fuera de Domain.
6. **Active Record** en `common/models/<BC>/` se trata como persistencia del BC (Infrastructure), sin exigir Entities/Aggregates puros en esta etapa.
7. Durante la migración: **lectura dual** legacy (`metadata/bioenlace`) + colocalizado; si el mismo `intent_id` existe en ambos, gana el colocalizado.

## Alternativas descartadas

- Un solo `Application/` global del monorepo (layer-first sin BC).
- Agrupadores `Core/` / `Support/` en el slot de dominio del espejo.
- Sustituir YAML de flows por PHP o JSON sin ganancia.
- Domain model puro sin AR en el mismo programa.

## Consecuencias

- `IntentSchemaPaths` / `ProductMetadataPaths` descubren raíces legacy y DDD.
- `AgentBoundedContextMap` fija el BC dueño de cada `agent_id` antes de migrar policies.
- Docs de arquitectura y tipología de metadata se actualizarán al cerrar; hasta entonces este ADR manda sobre “¿dónde va este YAML?”.
- Relacionado: [runtime-datos-vs-metadata.md](./runtime-datos-vs-metadata.md) (maestros → BD); [captura-clinica-contratos-yii-vs-yaml.md](./captura-clinica-contratos-yii-vs-yaml.md) (integridad no va en YAML). Los knobs de **negocio** dejan de ser excepción “composición”: pasan a PHP/BD según este ADR.