# Capabilities UI nativa (RBAC fuera de intents)

## Contexto

Tras [autorizacion-solo-por-intents.md](./autorizacion-solo-por-intents.md), los permisos assignables en admin eran solo `intent_id`. Eso cubre bien el **asistente NL** y flujos con `open_ui`, pero no todas las **UIs nativas** (web Yii, Flutter Personal de Salud) que llaman API sin header `X-Flow-Intent-Id`:

- Tablero de guardia (ingreso, triage, atender, retiro, operaciones staff)
- Captura y lectura de **encounter** clínico
- Panel home staff (`/api/home/panel`) con flags operativos

En paralelo convivían:

1. **Rutas API** (`/api/...`) en `auth_item_child`
2. **Capabilities UX** en `home-panel-manifest.yaml` (visibilidad de botones, no sustituyen RBAC)
3. **Permisos legacy** (`analisis`, `front_ver_historial_paciente`, `listado_pacientes` como padre de rutas guardia)
4. **Dominio** (`EncounterAccessService`, reglas de episodio guardia)

Síntoma típico: botón visible en manifiesto → **403** en API, o rol con intent de tablero sin grant a rutas de ingreso/triage.

## Decisión

Introducir **capabilities UI nativa** como segundo tipo de permiso assignable, declaradas en YAML y sincronizadas a `auth_item`:

| Tipo | Clave | Uso |
|------|-------|-----|
| Intent | `intent_id` | Asistente, flows NL, métricas staff con `open_ui` |
| Capability | `guardia.*`, `encounter.*`, `panel.*` | UIs nativas web/móvil + rutas API enlazadas |

### Capas (orden de evaluación)

```
RBAC ruta (rol → capability → /api/...) 
  → CapabilityAccessService (UX/manifiesto, exclusiones por rol)
  → DomainOperationAuthorizer / servicios dominio (dueño del recurso)
```

- **YAML capabilities:** `common/metadata/bioenlace/permission/capabilities/*.yaml` — `routes`, `default_roles`, `related_intents` (enlace opcional intent → capability).
- **Manifiesto panel:** `home-panel-manifest.yaml` referencia `capability_id` en CTAs EMER; no reemplaza grants RBAC.
- **Admin:** pestaña «Capabilities UI nativa» en catálogo de permisos; asignación de roles paritaria a intents.
- **Legacy:** `legacy-permission-aliases.yaml` + `intent-grant-migration-map.yaml` (`capability_grant_sources`) migran grants sin SQL manual.

### Principios heredados

- Sin hardcode de pantalla/rol en orquestadores: reglas en YAML + servicios genéricos (`CapabilityAccessService`, `GuardiaBoardCapabilityService`).
- YAML = composición y knobs; «¿puede persistirse sobre este encounter?» sigue en dominio Yii.

## Alternativas descartadas

- **Solo intents para guardia/encounter:** obliga a `X-Flow-Intent-Id` en clientes nativos o duplica intents artificiales por botón; rechazado.
- **Capabilities solo en manifiesto (sin auth_item):** no protege API; rechazado.
- **Mantener `listado_pacientes` como único padre de rutas guardia:** dejaba fuera Administrativo/enfermería; sustituido por capabilities + migración.

## Consecuencias

- Assignables en admin = **intents + capabilities** (no atributos `Entidad.atributo.*`).
- Deploy: `migrate` → `sync-capabilities` → `sync` → `migrate-grants` → `catalog-integrity/check`.
- Permisos `analisis` y `front_ver_historial_paciente` **deprecados**; reemplazo assignable: `encounter.capturar` y `encounter.ver_como_staff`.
- Integridad advierte rutas guardia solo bajo `listado_pacientes` y roles con legacy sin capability de reemplazo.
- Re-login tras cambios RBAC (`BioenlaceRbacRevision`).

## Referencias

- [rbac-catalogo-permisos.md](../arquitectura/rbac-catalogo-permisos.md)
- [autorizacion-solo-por-intents.md](./autorizacion-solo-por-intents.md)
- Metadata: `permission/capabilities/`, `permission/legacy-permission-aliases.yaml`, `ui/home-panel-manifest.yaml`
- CLI: `catalog-permission/sync-capabilities`, `catalog-permission/migrate-grants`, `catalog-integrity/check`
