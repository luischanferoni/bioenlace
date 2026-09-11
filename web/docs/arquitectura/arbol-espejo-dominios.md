# Árbol espejo por dominio

En cada capa del producto, el segmento que sigue a la capa es el **dominio** (o `platform`); el siguiente es la **entidad**.

```text
<capa>/<dominio|platform>/<entidad>/<archivo>
```

| Capa | Forma |
|------|--------|
| Servicios | `common/components/Domain/<Dominio>/…` · `common/components/Platform/<Área>/…` |
| Modelos | `common/models/<Dominio>/…` · `common/models/Platform/…` |
| Controllers API | `frontend/modules/api/v1/controllers/<dominio>/…` |
| UI JSON | `frontend/modules/api/v1/views/json/<dominio>/<entidad>/…` |
| Metadata | `common/metadata/bioenlace/<dominio>/…` · `…/platform/<área>/…` |
| Tests unitarios | `common/tests/unit/<dominio>/…` · `…/platform/<área>/…` |

## Fuente de verdad del conjunto de dominios

Las carpetas de primer nivel en `common/components/Domain/` (ids en minúsculas vía `ProductDomainCatalog`). Crear una carpeta ahí = declarar un dominio. No hay lista paralela que actualizar.

`platform` ocupa la **misma posición** que un dominio en las capas espejo; **no** vive bajo `Domain/`.

## Dos reglas

1. **Si pertenece a un dominio, espeja.** Misma grafía en todas las capas (`person`, no `persona`).
2. **Lo transversal no se disfraza de dominio.** Auth, chat, notificaciones, DataAccess genérico: raíz de `controllers/` (whitelist) o bajo `platform/` en metadata / views / tests.

## Criterio de asignación

El dominio lo decide **quién persiste / quién es dueño**, no el nombre del archivo.

| Duda | Criterio |
|------|----------|
| `person` vs `clinical` | `person` = perfil de cualquier rol; `clinical` = salud del paciente |
| `organization` vs `scheduling` | `organization` = oferta y estructura; `scheduling` = agenda y turnos |
| Motor vs dominio | Endpoint del rubro → dominio; motor genérico → `Platform/` |

## URLs y RBAC

Mover un controller a `controllers/<dominio>/` **no** cambia la URL pública ni la ruta RBAC: `DomainControllerMap` registra el id público sin dominio. Poner el dominio en la URL es una decisión aparte (clientes móviles + seeds).

## Invariantes en test

`common/tests/unit/platform/core/Product/ProductDomainTreeShapeTest.php` falla si:

- el primer nivel de una capa espejo no es dominio / `platform` / excepción documentada;
- aparece una grafía prohibida (`persona`, `integration`, `core`, `common` como slot de dominio);
- hay archivos planos donde el árbol ya está ordenado;
- existe `components/Domain/Platform/` o el namespace `Domain\Platform`.

Los `use` cruzados entre dominios (p. ej. `Scheduling\Turno` → `Organization\Efector`) y los pocos `Platform` → `Domain` vía registries siguen inventariados; un allowlist AST completo es un programa aparte, no parte de esta invariante de forma.

## Relacionado

- [common-components.md](./common-components.md) — Platform vs Domain en `components/`
- Regla Cursor: `.cursor/rules/common-components-organizacion.mdc`
