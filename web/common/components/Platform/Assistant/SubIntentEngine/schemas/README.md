## Schemas YAML (SubIntentEngine)

La **metadata de producto** (intents, reglas NL, atajos) vive en `common/metadata/bioenlace/assistant/`.  
Este directorio conserva el **contrato del motor** y documentación.

**Contrato de claves por paso (`subintents`) y raíz (`flow_submit`):** [`SUBINTENT_CONTRACT.md`](SUBINTENT_CONTRACT.md)

- Intents: `common/metadata/bioenlace/assistant/intents/`
- Canal conversacional (copy): `common/metadata/bioenlace/assistant/conversational-channel.yaml`
- Atajos: `common/metadata/bioenlace/assistant/assistant-shortcuts.yaml`
- Permisos dominio: `common/metadata/bioenlace/permission/domain-operation-policies.yaml`
- DataAccess staff: `Core/DataAccess/schemas/data-access-config/`

Resolución de rutas: `common\components\Platform\Core\Product\ProductMetadataPaths` y `Assistant\Catalog\IntentSchemaPaths`.
