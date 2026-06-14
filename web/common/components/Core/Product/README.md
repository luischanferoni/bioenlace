# Core / Product

Resolución de metadata y registries del producto (agnósticos del rubro en código PHP).

| Clase | Uso |
|-------|-----|
| `ProductMetadataPaths` | Rutas YAML (`common/metadata/bioenlace/`) — intents, reglas NL, permisos, panel |
| `ProductRegistryConfig` | Acceso a `common/config/product-registries.php` — handlers cableados a motores |

Para otro vertical: copiar `metadata/bioenlace/` y `config/product-registries.php`; opcionalmente `productMetadataDir` en params.
