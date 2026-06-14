# Core / Product

| Clase | Uso |
|-------|-----|
| `ProductMetadataPaths` | Rutas YAML bajo `common/metadata/bioenlace/` |
| `ProductRegistryConfig` | Lee `common/config/product-registries.php` (`productRegistries` en params) |

Secciones del registry: `flowDraftHydrators`, `domainOperationPolicies`, `dataAccessScopeCheckers`, `dataAccessFilterResolvers`, `metricPresentationHandlers`, `dataAccessEditMutationHandlers`, `homePanelStaffPanelSliceResolvers`, `uiActionCatalogProviders`, `conversationalChannelProviders`, `homePanelSectionProviders`.

Para otro vertical: reemplazar metadata + `product-registries.php` (opcional `productMetadataDir` en params-local).
