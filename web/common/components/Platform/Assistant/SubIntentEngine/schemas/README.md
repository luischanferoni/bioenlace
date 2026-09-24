## Schemas YAML (SubIntentEngine)

La **metadata de producto** (intents, reglas NL, atajos) vive en `common/metadata/bioenlace/`.  
Este directorio conserva el **contrato del motor** y documentación.

**Contrato de claves del statechart (`states`) y raíz (`flow_submit`):** [`SUBINTENT_CONTRACT.md`](SUBINTENT_CONTRACT.md)

- Intents: `common/metadata/bioenlace/<dominio|platform>/intents/`
- Canal guide (prompt): `common/components/Platform/Assistant/Channels/Guide/prompt.yaml`
- Atajos: `common/components/Platform/Assistant/Application/assistant-shortcut-group-labels.yaml`
- Permisos dominio: `common/metadata/bioenlace/platform/permission/domain-operation-policies.yaml`
- DataAccess staff: `Core/DataAccess/schemas/data-access-config/`

Resolución de rutas: `common\components\Platform\Core\Product\ProductMetadataPaths` y `Assistant\Catalog\IntentSchemaPaths`.
