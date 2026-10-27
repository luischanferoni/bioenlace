# Fase 01 — Resolver de rol operativo

## Objetivo

Una sola pieza que responda «¿qué rol opera este usuario ahora?» con una clave canónica, reutilizando lo que ya existe, y que ese rol viaje al panel y al top menu.

## Tareas

- Crear `common/components/Platform/Core/Permission/OperativeRoleResolver.php` (no en `Platform/Ui/Home`: lo necesitan también menú y API).
  - Fuente 1: `servicios.item_name` del PES en sesión (`ProfesionalEfectorServicio` + `Servicio`), que es el rol operativo real.
  - Fuente 2: roles RBAC del usuario vía `RbacRoleQueryService::getUserRoles()`.
  - Normalización y alias en `permission/metadata/roles.yaml` (`OperationalRoleManifest`), sin mapa hardcodeado en PHP. El alias `Enfermeria`/`enfermeria` cubre las dos variantes en uso.
  - Precedencia multicrol declarada en `precedence` de `roles.yaml`.
  - API: `resolve(): ?string` (clave canónica o null), `resolveAll(): list<string>` (todas las claves presentes, para diagnóstico).
- Exponer `role` en `HomePanelService::buildPanel()` (campo nuevo en la respuesta de `GET /api/v1/home/panel`).
- Sin cache propia: la consulta de PES es una sola por request y `OperationalRoleManifest` ya cachea el YAML en estático.

## Criterio de salida

- Tests unitarios: sólo PES, sólo RBAC, ambos en conflicto, sin roles, guest. (El caso superadmin queda cubierto por «sin roles» → null: el panel sigue resolviendo por encounter.)
- La API devuelve `role` y no cambia ningún campo existente.
- Cero comparaciones nuevas de strings de rol fuera del resolver y de la metadata.

## Estado

Implementado (modelo revisado con producto).

- `OperationalRoleManifest` + `permission/metadata/roles.yaml` (alias y orden por defecto; fuente única, sin mapa en PHP).
- `OperativeRoleResolver` (`Platform/Core/Permission`): manda el servicio elegido en la sesión. Los roles RBAC sólo aportan el valor por defecto cuando todavía no hay servicio elegido.
- `HomePanelService::buildPanel()` expone `role` (null cuando la audiencia no es staff).
- Tests: `OperationalRoleManifestTest` (alias, default por precedencia, roles desconocidos) y `OperativeRoleResolverTest` (servicio gana a RBAC, RBAC como default, guest, servicio sin rol declarado).

Pendiente: correr la suite en PHP 8.3 (el entorno local sólo tiene 7.4 y 8.0; el autoload de Composer exige >= 8.1). Validado con `php -l` y con un harness que ejercita `resolveFor()` y el manifest contra el YAML real.
