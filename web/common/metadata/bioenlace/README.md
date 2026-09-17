# Metadata del producto Bioenlace

La metadata canónica **no** vive en este árbol. Solo queda este README como ancla histórica.

| Qué | Dónde |
|-----|--------|
| Flows / intents | `components/Domain/<BC>/Application/Flows/intents/` y `Platform/Assistant/Application/Flows/intents/` |
| Assistant (catalog, routing, schemas, prompts, ui-text) | `components/Platform/Assistant/…` |
| UI manifests | `components/Platform/Ui/Presentation/` |
| Permission | `components/Platform/Core/Permission/metadata/` |
| AI | `components/Platform/Ai/Application/` |
| Agents knobs | PHP `Domain/<BC>/Application/Agents/*AgentPolicy` + `AgentPolicyRegistry` |
| Catálogos de negocio | PHP `Domain/<BC>/Domain/*Catalog` |
| ACL externos | `Domain/<BC>/Infrastructure/External/` |

ADR: [`ddd-bounded-contexts-capas-y-metadata.md`](../../../docs/decisions/ddd-bounded-contexts-capas-y-metadata.md).
