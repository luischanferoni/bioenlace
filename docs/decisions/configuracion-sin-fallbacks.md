# Configuración sin fallbacks silenciosos

## Decisión

Un valor de configuración o de negocio faltante **no se reemplaza por un literal inventado**. Si
el dato es necesario para calcular, cobrar, autorizar o mostrar una decisión, su ausencia es un
error de configuración y se **falla explícito** con la clave o la ruta.

Un **default legítimo** es el que está declarado en la fuente y se lee por el loader, una
colección donde `[]` significa “no hay elementos”, un valor técnico sin dependencia de negocio o
un `null` que el contrato documenta como “sin valor”.

**Regla publicada:** `.cursor/rules/configuracion-sin-fallbacks.mdc` (transversal). Complementa
[valores derivados: una sola fuente](valores-derivados-fuente-unica.md).

## Qué cambia según el tipo de `catch`

| Tipo | Decisión |
|------|----------|
| **Configuración** (YAML, manifiesto, catálogo, precio) | Lanza `InvalidConfigException` con ruta/clave; no devuelve `0`, `[]` ni un default |
| **Integridad de catálogo** | Agrega un error o warning al resultado; no omite el archivo inválido |
| **I/O externo** (proveedor, push, STT) | Puede devolver `null`/`false`, pero **loguea** y el llamador decide |
| **Data source / autorización** | Fail-closed o vacío **con log**; el error no queda oculto |
| **Index runtime** | `continue` **con `Yii::error`**; el producto arranca, pero el catálogo incompleto es visible |

## Casos resueltos

| Zona | Antes | Ahora |
|------|-------|-------|
| Metadata de precios | `margin`, impuestos, COGS e infra devolvían `0`/`1` | `requiredFloat()` y fallo explícito |
| Metadata clínica | `max_diagnosticos ?? 8`, `context_term_boost ?? 0.0` | Lee la clave o falla |
| Metadata SNOMED | Umbral y límites con default | Lee la clave o falla |
| YAML de UI/contexto | `warning` y devolvía config vacía | `InvalidConfigException` |
| Integridad RBAC | YAML inválido se omitía | Error/warning en el reporte |
| Roles | Listas inline en servicios/controladores | Banderas en `roles.yaml` + `OperationalRoleManifest` |
| Cliente Flutter | Catálogo de clases devolvía `[]` | `ApiContractException` si falta la clave |
| `max_pes` nulo | Se copiaba como `1` | Parte del uso actual del contrato |

## Alternativas descartadas

- **Fallback al valor “de siempre”.** Convierte un error de configuración en una política que nadie
  declaró y que varía según el canal.
- **`warning` y continuar.** El log se pierde y el sistema opera degradado sin que nadie lo note.
- **Borrar todos los `catch`.** No todo `catch` es configuración: I/O opcional y autorización
  fail-closed tienen semántica propia. El triage distingue el tipo.

## Consecuencias

- Un despliegue con un YAML inválido falla al cargar en vez de operar con prompts o permisos
  vacíos.
- Los errores de catálogo aparecen en integridad y en logs, no como listas vacías.
- El comportamiento de negocio no cambió: los catálogos versionados tienen todas las claves.

## Enforcement

- `SourceOfTruthDerivedValuesTest` y los tests de cada catálogo.
- `web/tools/audit-derived-values.php`:
  - `catalog_numeric_fallback`: `?? <número>` en `Platform/Core/Product`.
  - `inline_role_list`: listas de roles inline.
  - `config_catch_default`: `catch` que devuelve default en `Platform/Core/{Product,Permission}`.
- Baseline versionado; el tope sólo baja. Corre en `.githooks/pre-commit`.
- `php tools/sync-agent-rules.php --check` mantiene la regla publicada.

Taxonomía ampliada: [arquitectura/metadata-yaml-uso.md § Configuración faltante](../arquitectura/metadata-yaml-uso.md#configuración-faltante-fallar-explícito).
