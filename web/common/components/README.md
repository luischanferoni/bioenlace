# Organización de `common/components`

Código reutilizable por web, API, consola y jobs.

**Antes de tocar este árbol**, leer: [common-components.md](../../docs/arquitectura/common-components.md).

## Tres ejes top-level

| Capa | Carpeta | Namespace | Qué va |
|------|---------|-----------|--------|
| **Motores** | [`Platform/`](./Platform/) | `common\components\Platform\…` | IA, asistente, DataAccess, permisos genéricos, UI JSON |
| **Shared** | [`Shared/`](./Shared/) | `common\components\Shared\…` | Infra técnica y tipos transversales |
| **Rubro Bioenlace** | [`Domain/`](./Domain/) | `common\components\Domain\…` | Clinical, Scheduling, Person, Organization, Terminology, … |

El **comportamiento del producto** (intents, reglas NL, panel home, permisos declarativos) vive en metadata colocalizada + knobs PHP. El **cableado dominio → motor** en **`common/config/product-registries.php`**.

Para otro rubro: reemplazar `Domain/` (o apuntar otro paquete), metadata y `product-registries.php`; **`Platform/`** y **`Shared/`** se reutilizan.

## Reglas rápidas

- **Dominios de negocio** bajo `Domain/{Clinical|Scheduling|Person|Organization|…}/`. **No** usar `Services/` (eliminado).
- **No** carpetas clínicas sueltas fuera de `Domain/Clinical/` (`Emergency/`, `Inpatient/` van ahí).
- **ACL externos** bajo `<BC>/<Modulo?>/Infrastructure/External/`, no en `Clinical/Infrastructure/` ni en Shared.
- **`Platform/Assistant/`**: motores del asistente — ver [Assistant/README.md](./Platform/Assistant/README.md).
- **`Platform/`** sin reglas de negocio por rubro; lo específico va en metadata + registries + `Domain/`.

## Documentación

- [common-components.md](../../docs/arquitectura/common-components.md) — fuente de verdad
- [Platform/README.md](./Platform/README.md) — motores agnósticos
- [Shared/README.md](./Shared/README.md) — infra transversal
- [Domain/README.md](./Domain/README.md) — negocio salud Bioenlace
- [common/README.md](../README.md) — vista `common/` completa
