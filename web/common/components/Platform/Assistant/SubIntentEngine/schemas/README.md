## Schemas YAML (SubIntentEngine)

La **metadata de producto** (intents, reglas NL, atajos) vive en `common/metadata/bioenlace/`.  
Este directorio conserva el **contrato del motor** y documentación.

**Contrato de claves por paso (`subintents`) y raíz (`flow_submit`):** [`SUBINTENT_CONTRACT.md`](SUBINTENT_CONTRACT.md)

- Intents: `common/metadata/bioenlace/<dominio|platform>/intents/`
- Canal guide (prompt): `common/metadata/bioenlace/platform/assistant/channels/Guide/prompt.yaml`
- Atajos: `common/metadata/bioenlace/platform/assistant/assistant-shortcut-group-labels.yaml`
- Permisos dominio: `common/metadata/bioenlace/platform/permission/domain-operation-policies.yaml`
- DataAccess staff: `Core/DataAccess/schemas/data-access-config/`

Resolución de rutas: `common\components\Platform\Core\Product\ProductMetadataPaths` y `Assistant\Catalog\IntentSchemaPaths`.
