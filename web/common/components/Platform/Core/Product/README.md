# Core / Product

| Clase | Uso |
|-------|-----|
| `ProductMetadataPaths` | Rutas YAML bajo `common/metadata/bioenlace/` |
| `ClientContextMetadata` | Reglas web staff vs paciente (`ui/client-context.yaml`) |
| `UiJsonDomainMetadata` | Mapeo entidades UI JSON (`ui/json-domains.yaml`) |
| `UiScreenParamsMetadata` | Expansión params pantallas (`ui/screen-params.yaml`) |
| `UiSelectOptionSourceMetadata` | Fuentes select UI (`ui/select-option-sources.yaml`) |
| `ProductRegistryConfig` | Lee `common/config/product-registries.php` (`productRegistries` en params) |

Secciones del registry: `flowDraftHydrators`, `domainOperationPolicies`, `dataAccessScopeCheckers`, `dataAccessFilterResolvers`, `metricPresentationHandlers`, `dataAccessEditMutationHandlers`, `homePanelStaffPanelSliceResolvers`, `uiActionCatalogProviders`, `conversationalChannelProviders`, `hintCandidateProviders`, `uiScreenParamsExpanders`, `uiSelectOptionSourceProviders`, `uiCatalogOptionDefinitions`, `homePanelSectionProviders`.

Para otro vertical: reemplazar metadata + `product-registries.php` (opcional `productMetadataDir` en params-local).
