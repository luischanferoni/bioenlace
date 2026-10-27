# Plan — Paneles web personalizados por rol

Programa de trabajo (varios PR). Temporal: al terminar se borra esta carpeta y lo vigente pasa a `producto/` y `decisions/`.

Lectura: [overview.md](./overview.md), [design.md](./design.md), fases en [phases/](./phases/).

## Seguimiento

| Fase | Estado |
|------|--------|
| 00 Inventario de roles y matriz de paneles | pendiente |
| 01 Resolver de rol operativo | implementado (falta correr la suite en PHP 8.3) |
| 02 Resolución de dos ejes (rol × encounter) | implementado (AdminEfector elige encounter: pendiente, requiere wizard) |
| 03 Audiencia staff ampliada y RBAC de gestión | implementado (AdminMinisterio en audiencia + capability + migración) |
| 04 Secciones por rol y encounter | implementadas las 3 secciones del MVP; quedan definiciones de producto |
| 05 Top menu por metadata | descartada (el top menu se muestra siempre; cambio revertido) |
| 06 QA por rol y compatibilidad de clientes | pendiente |

## Código involucrado

| Área | Ubicación |
|------|-----------|
| Resolver de rol (nuevo) | `common/components/Platform/Core/Permission/OperativeRoleResolver.php` |
| Roles RBAC (existente) | `common/components/Platform/Core/Permission/RbacRoleQueryService.php` |
| Normalización de actor | `common/components/Domain/Clinical/Capture/Domain/Catalog/CaptureActorCatalog.php` |
| Audiencia del panel | `common/components/Platform/Ui/Home/Service/HomePanelAudienceResolver.php` |
| Resolución de panel | `common/components/Platform/Ui/Home/Service/HomePanelManifest.php` |
| Orquestador | `common/components/Platform/Ui/Home/Service/HomePanelService.php` |
| Variantes de panel | `common/components/Platform/Ui/Home/Service/HomePanelStaffPanelSliceRegistry.php` |
| Registro producto | `common/config/product-registries.php` |
| Manifest de panel | `common/components/Platform/Ui/Presentation/home-panel-manifest.yaml` |
| Metadata de cliente | `common/components/Platform/Ui/Presentation/client-context.yaml` |
| Metadata de contexto | `common/components/Platform/Core/Product/ClientContextMetadata.php` |
| Sections existentes | `common/components/Domain/*/Home/Sections/` |
| Capability del panel | `common/components/Platform/Core/Permission/metadata/capabilities/panel.yaml` |
| Layout / top menu | `frontend/components/Layout/MainLayoutPageBuilder.php`, `frontend/views/layouts/main.php` |
| Tests | `common/tests/unit/platform/ui/HomePanelManifestTest.php` |

## Reglas del programa

1. El manifest es **presentación**, no RBAC: los grants viven en `permission/metadata/capabilities/` y se sincronizan a `auth_item`.
2. Ningún nombre de rol hardcodeado en PHP, vistas ni page builders: roles, alias y precedencia salen del manifest o de `client-context.yaml`.
3. El manifest lo consumen web **y** las dos apps móviles vía `GET /api/v1/home/panel`: no hay requisito de retrocompatibilidad, los clientes se actualizan en el mismo programa.
4. Los providers nuevos viven en su dominio y se registran en `product-registries.php`.
5. Ningún panel de gestión expone datos que el rol no puede ver en la API.

## Al cerrar

| Contenido | Destino |
|-----------|---------|
| Matriz rol → panel → top menu, comportamiento final | `producto/superficies-ui.md` |
| Decisión de resolución por dos ejes y semántica de overlay | `decisions/paneles-por-rol.md` (nuevo) |
| Alta de roles de gestión en audiencia y capabilities | `arquitectura/rbac-catalogo-permisos.md` |
| Secciones y métricas del panel | `producto/` (documento del panel de inicio) |
| Checklist de verificación por rol | `qa/quien-puede-que.md` + `qa/admin_efector/` + `qa/staff/` |

## Relacionado

- Roles desde BD: `web/docs/qa/roles-desde-bd.md`
- Capabilities de UI nativa: `web/docs/decisions/autorizacion-capabilities-ui-nativa.md`
- Uso de metadata YAML: `web/docs/arquitectura/metadata-yaml-uso.md`
