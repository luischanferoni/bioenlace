# Fase 04 — Resoluciones (rutas/acciones) y cierre

## Objetivo

Unificar los cinco cachés de metadata resuelta y, con eso, cerrar el plan: ninguna clave
armada a mano, todo TTL con nombre, toda caché invalidable.

## Tamaño

**Medio (1 día).**

## Sitios

| Archivo | Dato | Invalidación actual |
|---------|------|---------------------|
| `ActionDiscoveryService` | acciones disponibles (2 claves) | sólo TTL |
| `ActionMappingService` | acciones por usuario | `delete` por usuario |
| `AllowedRoutesResolver` | rutas permitidas (2 claves) | sólo TTL + sentinel `'__empty__'` |
| `AssistantShortcutGroupRegistry` | snapshot de atajos | `delete` + TTL |
| `YamlIntentCatalogService` | catálogo de intents | `300` hardcodeado, sin invalidación |

## Tareas

- [ ] Migrar los 5 a `CacheStore`, con namespace `rbac.routes`, `rbac.actions`,
  `assistant.catalog`.
- [ ] Reemplazar el sentinel `'__empty__'` por el manejo interno del helper.
- [ ] Conectar la invalidación a `BioenlaceRbacRevision`: cuando cambian permisos o rutas, se
  hace `bumpRevision`, no se espera el TTL.
- [ ] `YamlIntentCatalogService`: dejar de usar `300` suelto; invalidar al desplegar catálogo.
- [ ] Bajar el `md5(` a 0 en el contador del inventario si se agrega al auditor.
- [ ] Cierre: volcar la política a `decisions/` y borrar esta carpeta.

## Aceptación

- Cambiar un permiso se refleja sin esperar 5 minutos ni reiniciar.
- Ninguna clase de `Platform/Assistant/UiActions` ni `Platform/Core/Permission` arma claves.
- `grep "Yii::\$app->cache"` sólo aparece en `Shared/Infrastructure/Cache`.

## Riesgos

- **RBAC y acciones son camino caliente del asistente**: una invalidación mal conectada deja
  a un usuario viendo acciones que ya no tiene, o sin ver las nuevas. Probar con dos usuarios
  y un cambio de permisos en vivo.
- El caché de catálogo YAML usa `300` sin invalidación: si se conecta a revisión, hay que
  garantizar que el bump ocurra en el deploy, no en cada request.
