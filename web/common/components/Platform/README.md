# `Platform/` — motores agnósticos del rubro

Namespace base: `common\components\Platform\…`

Código **reutilizable en otro producto** cambiando metadata (`common/metadata/{producto}/`) y registries (`product-registries.php`). **No** incluir reglas de negocio clínico, turnos ni organización de salud aquí.

## Subcarpetas

| Carpeta | Contenido |
|---------|-----------|
| **`Ai/`** | Clientes IA, STT genérico, embeddings, cost tracking |
| **`Assistant/`** | IntentEngine, SubIntentEngine, Chat — interpretan YAML de producto |
| **`Core/`** | DataAccess, permisos de dominio (autorizadores genéricos), push, `Core/Product/` |
| **`Ui/`** | UI JSON, panel home (motor), grid |
| **`Infra/`** | Utilidades técnicas (migraciones, deduplicación) |
| **`Legacy/`** | Compatibilidad técnica (p. ej. webvimark) |

## Qué **no** va aquí

- Encounters, guardia, internación, prescripción → `../Domain/Clinical/`
- Turnos, agenda → `../Domain/Scheduling/`
- Plugins de catálogo UI / panel home **del rubro** → clases en `Domain/…`, registradas en `product-registries.php`
- Metadata de intents / permisos → `common/metadata/bioenlace/`

## Referencias

- [Assistant/README.md](./Assistant/README.md)
- [Core/Product/README.md](./Core/Product/README.md)
- [../README.md](../README.md)
