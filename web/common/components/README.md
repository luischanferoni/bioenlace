# Organización de `common/components`

Código reutilizable por web, API, consola y jobs.

**Antes de tocar este árbol**, leer: [common-components.md](../../docs/arquitectura/common-components.md).

## Dos capas (separación rubro vs motores)

| Capa | Carpeta | Namespace | Qué va |
|------|---------|-----------|--------|
| **Motores / plataforma** | [`Platform/`](./Platform/) | `common\components\Platform\…` | IA, asistente, DataAccess, permisos genéricos, UI JSON, infra técnica |
| **Rubro Bioenlace (salud)** | [`Domain/`](./Domain/) | `common\components\Domain\…` | Clínico, turnos, personas, organización, integraciones, terminología |

El **comportamiento del producto** (intents, reglas NL, panel home, permisos declarativos) vive en **`common/metadata/bioenlace/`**. El **cableado dominio → motor** en **`common/config/product-registries.php`**.

Para otro rubro: reemplazar `Domain/` (o apuntar otro paquete), metadata y `product-registries.php`; **`Platform/`** se reutiliza.

## Reglas rápidas

- **Dominios de negocio** bajo `Domain/{Clinical|Scheduling|Person|Organization|…}/`: lógica en `*/Service/`. **No** usar `Services/` (eliminado).
- **No** carpetas clínicas sueltas fuera de `Domain/Clinical/` (`Emergency/`, `Inpatient/` van ahí).
- **`Platform/Assistant/`**: motores del asistente — ver [Assistant/README.md](./Platform/Assistant/README.md).
- **`Platform/`** sin reglas de negocio por rubro; lo específico va en metadata + registries + `Domain/`.

## Documentación

- [common-components.md](../../docs/arquitectura/common-components.md) — fuente de verdad
- [Platform/README.md](./Platform/README.md) — motores agnósticos
- [Domain/README.md](./Domain/README.md) — negocio salud Bioenlace
- [common/README.md](../README.md) — vista `common/` completa
