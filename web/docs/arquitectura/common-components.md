# `common/components` — organización y responsabilidades

**Leer este documento antes de crear, mover o refactorizar código en `web/common/components/`.**

Código reutilizable por API v1, consola, jobs y (legacy) frontend Yii. La regla de oro: **separar motores (`Platform/`) del rubro (`Domain/`)**, y declarar el producto en metadata + registries.

## Dos capas top-level

| Capa | Ruta | Namespace | Contenido |
|------|------|-----------|-----------|
| **Plataforma / motores** | `components/Platform/` | `common\components\Platform\…` | IA, asistente, DataAccess, permisos genéricos, UI JSON, infra |
| **Rubro Bioenlace** | `components/Domain/` | `common\components\Domain\…` | Clínico, turnos, personas, organización, integraciones, terminología |

**Metadata del producto:** `common/metadata/bioenlace/` (intents, reglas NL, permisos, panel).

**Cableado dominio → motor:** `common/config/product-registries.php` (`productRegistries` en `params.php`).

Para otro rubro: nuevo `Domain/`, metadata y registries; **`Platform/`** se mantiene.

## `Platform/` — motores (agnósticos)

| Carpeta | Contenido |
|---------|-----------|
| **`Platform/Assistant/`** | IntentEngine, SubIntentEngine, Chat — ver [Assistant/README.md](../../common/components/Platform/Assistant/README.md) |
| **`Platform/Core/`** | DataAccess, permisos, push, `Core/Product/` |
| **`Platform/Ui/`** | Pantallas JSON, panel home (motor), grid |
| **`Platform/Ai/`** | Proveedores IA, STT genérico, embeddings |
| **`Platform/Infra/`**, **`Platform/Legacy/`** | Técnico transversal |

**Prohibido** en `Platform/`: reglas de negocio clínico, listas de intents por rubro, `if (intentId === …)`.

## `Domain/` — negocio salud

| Carpeta | Contenido |
|---------|-----------|
| **`Domain/Clinical/`** | Encounters, guardia, internación, prescripción, lab, `Text/` |
| **`Domain/Scheduling/`** | Turnos, agenda, quirófano |
| **`Domain/Person/`** | Personas, registro |
| **`Domain/Organization/`** | Efectores, PES, sesión operativa |
| **`Domain/Integrations/`** | Sistemas externos (SISSE, receta, MPI, LIS) |
| **`Domain/Terminology/`** | SNOMED |

**No crear** carpetas clínicas sueltas fuera de `Domain/Clinical/` (`Emergency/`, `Inpatient/` van ahí).

## Patrones dentro de un dominio

- Servicios: `{Dominio}/{Subdominio}/Service/*.php`
- Plugins para motores: registrar en `product-registries.php`, implementación en `Domain/…`

## Motores vs metadata vs negocio

| Capa | Ubicación | Responsabilidad |
|------|-----------|-----------------|
| **Motores** | `Platform/Assistant/…`, `Platform/Core/DataAccess`, `Platform/Core/Product/` | Interpretar manifiestos; sin reglas por rubro en PHP |
| **Metadata producto** | `common/metadata/bioenlace/` | Qué hacer (flows, métricas, permisos, panel) |
| **Plugins dominio** | `product-registries.php` + clases en `Domain/` | Catálogos UI, scope, políticas, panel home |
| **Negocio** | `Domain/Clinical/`, `Domain/Scheduling/`, … | Persistencia, reglas, autorización de recurso |

## Dónde ubicar código nuevo

| Necesidad | Ubicación |
|-----------|-----------|
| Guardia, triage | `Domain/Clinical/Emergency/Service/` |
| Mapa camas, ingreso internación | `Domain/Clinical/Inpatient/Service/` |
| Encounter, care plan | `Domain/Clinical/Service/` |
| Turno, agenda | `Domain/Scheduling/Service/` |
| Efector, PES | `Domain/Organization/Service/` |
| Motor asistente / flow genérico | `Platform/Assistant/` |
| Intent / YAML producto | `common/metadata/bioenlace/assistant/` |
| Proveedor IA | `Platform/Ai/` |
| Cliente externo salud | `Domain/Integrations/` |
| Texto clínico pre-IA | `Domain/Clinical/Text/` |

## Referencias

- [README en código](../../common/components/README.md)
- [Platform/README.md](../../common/components/Platform/README.md)
- [Domain/README.md](../../common/components/Domain/README.md)
- [Asistente — motores](./asistente-motores.md)
