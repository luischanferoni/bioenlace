# Core / Product

| Clase | Uso |
|-------|-----|
| `ProductMetadataPaths` | Rutas YAML colocalizadas (`components/Domain|Platform/…`) |
| `AgentPolicyRegistry` / `AutonomousAgentMetadata` | Knobs de agentes tipados (`*AgentPolicy`) |
| `ClientContextMetadata` | Reglas web staff vs paciente (`Ui/Presentation/client-context.yaml`) |
| `ProductDomainCatalog` | Dominios canónicos desde `components/Domain/` (árbol espejo) |
| `UiJsonDomainIndex` | Dominio de entidad UI desde `views/json/<dominio>/` (no mapa a mano) |
| `UiScreenParamsMetadata` | Expansión params pantallas (`Ui/Presentation/screen-params.yaml`) |
| `UiSelectOptionSourceMetadata` | Fuentes select UI (constantes PHP) |
| `ProductRegistryConfig` | Lee `common/config/product-registries.php` (`productRegistries` en params) |

Secciones del registry: `flowDraftHydrators`, `domainOperationPolicies`, `dataAccessScopeCheckers`, `dataAccessFilterResolvers`, `metricPresentationHandlers`, `dataAccessEditMutationHandlers`, `homePanelStaffPanelSliceResolvers`, `uiActionCatalogProviders`, `conversationalChannelProviders`, `hintCandidateProviders`, `uiScreenParamsExpanders`, `uiSelectOptionSourceProviders`, `uiCatalogOptionDefinitions`, `homePanelSectionProviders`.

Para otro vertical: reemplazar Domain + metadata colocalizada + `product-registries.php`.
