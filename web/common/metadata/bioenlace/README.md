# Metadata del producto Bioenlace

**Migración DDD (fases 01–05):** la metadata canónica ya no vive aquí como árbol de YAML de producto.

| Qué | Dónde ahora |
|-----|-------------|
| Flows / intents | `components/Domain/<BC>/Application/Flows/intents/` y `Platform/Assistant/Application/Flows/intents/` |
| Assistant (catalog, routing, schemas, prompts, ui-text) | `components/Platform/Assistant/…` |
| UI manifests | `components/Platform/Ui/Presentation/` |
| Permission | `components/Platform/Core/Permission/metadata/` |
| AI | `components/Platform/Ai/Application/` |
| Agents knobs | PHP `Domain/<BC>/Application/Agent/*AgentPolicy` + `AgentPolicyRegistry` |
| Catálogos de negocio | PHP `Domain/<BC>/Domain/*Catalog` |

ADR: [`ddd-bounded-contexts-capas-y-metadata.md`](../../../docs/decisions/ddd-bounded-contexts-capas-y-metadata.md).

`ProductMetadataPaths::baseDir()` sigue apuntando a esta carpeta por compat; puede quedar vacía salvo este README hasta el cierre del plan (fase 07).

Fase 06 hecha: ACL en `Domain/<BC>/Infrastructure/External/`. Pendiente fase 07: pulir docs/planes que aún citen paths viejos.
