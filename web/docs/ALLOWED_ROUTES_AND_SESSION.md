# Rutas permitidas: sesión webvimark y caché

## Resumen

- **[`AllowedRoutesResolver`](../common/components/Actions/AllowedRoutesResolver.php)** centraliza cómo se obtiene el conjunto de rutas que un usuario puede usar para filtrar acciones descubiertas.
- **Sesión (webvimark):** tras el login, `AuthHelper::updatePermissions()` guarda en sesión `__userRoutes` (lista de rutas). Si el request es del usuario autenticado y coincide el conjunto de roles con el de sesión, **`UniversalQueryAgent::getAvailableActionsByRole`** usa esas rutas **sin** recorrer `getPermissionsByRole` en bucle.
- **Caché de aplicación:** clave `allowed_routes_map_u_{userId}` (TTL 30 min) y `target_routes_roles_{md5(roles)}` para mapas por rol.
- **Invalidación:** webvimark usa `runtime/__permissions_last_mod.txt`; al tocarse, `ensurePermissionsUpToDate()` refresca sesión. La caché de app expira por TTL; para forzar, usar `AllowedRoutesResolver::invalidateUserCache($userId)`.

## Observabilidad

- Categoría de log: `allowed-routes-resolver`, `universal-query-agent`, `action-mapping`.
- Mensajes: `session routes hit`, `app cache hit`, `targetRoutes from session`.

## API sin sesión PHP

Los clientes que autentican solo con token pueden no tener `__userRoutes` en sesión del servidor. En ese caso se usa **`Route::getUserRoutes($userId)`** (una consulta agrupada) + caché de app.
