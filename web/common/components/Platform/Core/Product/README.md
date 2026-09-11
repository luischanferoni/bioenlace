# Core / Product

| Clase | Uso |
|-------|-----|
| `ProductMetadataPaths` | Rutas YAML bajo `common/metadata/bioenlace/` (mapa: `metadata/bioenlace/README.md`) |
| `AutonomousAgentMetadata` | Knobs de agentes (`agents/{agent_id}.yaml`) |
| `ClientContextMetadata` | Reglas web staff vs paciente (`ui/client-context.yaml`) |
| `ProductDomainCatalog` | Dominios canónicos desde `components/Domain/` (árbol espejo) |
| `UiJsonDomainIndex` | Dominio de entidad UI desde `views/json/<dominio>/` (no mapa a mano) |
| `UiScreenParamsMetadata` | Expansión params pantallas (`ui/screen-params.yaml`) |
| `UiSelectOptionSourceMetadata` | Fuentes select UI (constantes PHP) |
| `ProductRegistryConfig` | Lee `common/config/product-registries.php` (`productRegistries` en params) |

Secciones del registry: `flowDraftHydrators`, `domainOperationPolicies`, `dataAccessScopeCheckers`, `dataAccessFilterResolvers`, `metricPresentationHandlers`, `dataAccessEditMutationHandlers`, `homePanelStaffPanelSliceResolvers`, `uiActionCatalogProviders`, `conversationalChannelProviders`, `hintCandidateProviders`, `uiScreenParamsExpanders`, `uiSelectOptionSourceProviders`, `uiCatalogOptionDefinitions`, `homePanelSectionProviders`.

Para otro vertical: reemplazar metadata + `product-registries.php` (opcional `productMetadataDir` en params-local).
