# Design — Paneles web personalizados por rol

## Decisiones

| Tema | Decisión | Motivo |
|------|----------|--------|
| Ejes de resolución | Dos ejes explícitos: `rol` y `encounter_class` | Los roles de gestión no tienen encounter; un solo eje deja sus paneles inalcanzables |
| Cruce con encounter | **Todo** rol de personal de salud se cruza con encounter class, AdminEfector incluido. Sólo el paciente no lo está | El trabajo real siempre ocurre en un encounter; el rol cambia el recorte, no el eje |
| Dónde se resuelve el rol | `OperativeRoleResolver` en `Platform/Core/Permission` | Lo usan panel, top menu y API; no es específico del home |
| Fuente del rol | El **servicio elegido en la sesión** (`servicios.item_name` del PES); los roles RBAC sólo son el valor por defecto cuando todavía no hay servicio elegido | El usuario elige el servicio al iniciar sesión y lo cambia desde el top menu: el contexto es estado de sesión, no un atributo fijo de la cuenta |
| Nombres de rol | Clave canónica en snake_case más tabla de alias en `permission/metadata/roles.yaml` | Evita `enfermeria` vs `Enfermeria` disperso (hoy `redirectRouteForCurrentUser()` ya compara mal); el manifest sólo usa claves canónicas |
| Multicrol | No hay desempate: el usuario elige servicio (y encounter) al iniciar sesión y puede cambiarlo desde el top menu. `precedence` sólo define el valor por defecto del wizard cuando aún no eligió | Evita paneles "sorpresa" para quien tiene dos roles |
| Configuración de paneles | Extender `home-panel-manifest.yaml` con `staff_by_role` | El manifest ya es el punto de composición; sigue siendo presentación, no RBAC |
| Semántica de overlay | `sections` reemplaza; `sections_append` agrega; `exclude_sections` quita; `title`/`layout` override | Reglas explícitas y testeables, sin merges implícitos de YAML |
| Variantes por rol | Resolver registrado en `product-registries.php`, mismo patrón que los slices de IMP | Permite override por `params-local` y apagado sin tocar core |
| Audiencia | `AdminMinisterio` (y los roles que defina la fase 00) entran a `audience.staff_roles` | Hoy caen en `fallback` y la capability no los cubre |
| Top menu | Metadata en `client-context.yaml` (`hide_servicio_navbar_roles`, `hide_encounter_navbar_roles`) | Ya existe el patrón; evita condicionales de rol en vistas |
| Ubicación de providers | `Domain/<BoundedContext>/Home/Sections/` + registro en `product-registries.php`; si la gramática del BC no admite `Home/` como plugin (p. ej. `Organization`, que sólo permite `Assistant` y `DataAccess`), va en `<Modulo>/Application/Presentation/` | Patrón dominante del repo sin romper `BoundedContextLayerShapeTest` |
| Panel médico | Sin cambios | Ya construido |
| Menú lateral | Se mantiene Inicio + Asistente; sólo cambia la etiqueta por rol vía page builder | Alcance mínimo de UI |
| Apps móviles | Los paneles de todos los roles se muestran también en las apps: contrato compartido, sin filtro por cliente | Decisión confirmada con producto |
| Retrocompatibilidad | No hay requisito de retrocompatibilidad; el contrato puede cambiar y se documenta en [arquitectura/contrato-api.md](../../arquitectura/contrato-api.md) | Confirmado con producto |

## Arquitectura de resolución

```
Request (web staff o app staff)
    ↓
HomePanelService::buildPanel()
    ↓
HomePanelAudienceResolver::resolve() → staff | patient | fallback
    ↓
OperativeRoleResolver::resolve()
    ├─ servicio elegido en sesión → servicios.item_name (manda)
    ├─ sin servicio elegido → roles RBAC + precedence (valor por defecto del wizard)
    └─ normalización por alias (permission/metadata/roles.yaml)
    ↓
HomePanelManifest::resolve(audience, encounterClass, role)
    ↓
cadena de fallback (ver abajo)
```

El rol y el encounter viven en la **sesión**: cambiar servicio o encounter type desde el top menu cambia el panel. El resolver se llama en cada request, no se cachea en sesión.

Cadena de resolución para staff (todo rol de personal de salud, AdminEfector incluido):

1. `panels.staff_by_role.<rol>.<encounter>`
2. `panels.staff_by_role.<rol>.""` — sesión todavía sin encounter (transición post-login)
3. `panels.staff.<encounter>` — con slices (`IMP` → `imp_surgical` / `imp_floor`)
4. `panels.staff_operations` — sin encounter en sesión
5. `panels.fallback`

Sólo `paciente` queda fuera del eje encounter.

Base de un overlay: si el overlay declara `sections`, reemplaza la composición y no necesita base. Si declara `sections_append` o `exclude_sections`, la base es el primer escalón posterior (3, 4 o 5) que exista para ese usuario. Un overlay que no declara `sections` ni `sections_append` es un error de configuración.

## Estructura YAML propuesta

Roles operativos en `permission/metadata/roles.yaml` (implementado en la fase 01):

```yaml
roles:
  admin_ministerio: AdminMinisterio
  nivel_central: nivelCentral
  admin_efector: AdminEfector
  administrativo: Administrativo
  facturista: facturista
  enfermeria: enfermeria
  medico: Medico

aliases:                # nombres RBAC y servicios.item_name → clave canónica
  AdminEfector: admin_efector
  Enfermeria: enfermeria
  nivelCentral: nivel_central
  # ...

precedence:             # desempate multicrol; gana el primero presente
  - admin_ministerio
  - nivel_central
  - admin_efector
  - administrativo
  - facturista
  - enfermeria
  - medico
```

Panel en `home-panel-manifest.yaml` (fase 02):

```yaml
panels:
  # Eje encounter (existente, sin cambios de forma)
  staff:
    EMER: { ... }
    AMB: { ... }
    VR: { ... }
    IMP: { ... }

  # Eje rol, cruzado con encounter. La clave vacía es "sesión sin encounter todavía".
  # Todo rol de personal de salud define sus paneles por encounter.
  staff_by_role:
    admin_efector:
      AMB:                                  # base: panels.staff.AMB
        title: Gestión del efector · ambulatorio
        sections_append: [efector_summary, active_professionals]
      IMP:
        title: Gestión del efector · internación
        sections_append: [efector_summary, active_professionals]
      "":
        layout: staff_dashboard
        title: Panel de Gestión del Efector
        exclude_sections: [staff_efector_modalidad_kpis]
        sections_append: [efector_summary, active_professionals]
    admin_ministerio:
      "":
        layout: staff_dashboard
        title: Panel de Red Ministerial
        sections: [network_supervision]
    enfermeria:
      IMP:
        title: Mis pacientes
        sections_append: [vital_signs_pending]
      EMER:
        sections_append: [vital_signs_pending]
    administrativo:
      AMB:
        sections_append: [admissions_pending]
```

Reglas de autoría del YAML:

- Un overlay puede declarar `sections`, `sections_append`, `exclude_sections`, `title`, `layout`. No se permiten otras claves.
- `sections` y `sections_append` no se combinan en el mismo overlay (error de configuración detectado en test).
- Toda clave de rol debe existir en `roles.yaml` (`aliases` → `roles`); los valores de alias son únicos.
- Todo `provider` referenciado debe existir en `homePanelSectionProviders`.

## Dónde vive cada pieza

| Pieza | Ubicación | Estado |
|-------|-----------|--------|
| Metadata de roles | `permission/metadata/roles.yaml` | implementado |
| Resolver de rol | `Platform/Core/Permission/OperativeRoleResolver.php` + `OperationalRoleManifest.php` | implementado |
| Overlay resolver | `Domain/*/Home/Application/Service/*RoleOverlayResolver.php` + registro | nuevo |
| Manifest + resolución | `Platform/Ui/Home/Service/HomePanelManifest.php` | modificar |
| Orquestador | `Platform/Ui/Home/Service/HomePanelService.php` | implementado (`role` en la respuesta) |
| Audiencia | `Platform/Ui/Home/Service/HomePanelAudienceResolver.php` | modificar |
| Metadata de cliente | `Platform/Core/Product/ClientContextMetadata.php` + `client-context.yaml` | modificar |
| Capabilities | `permission/metadata/capabilities/panel.yaml` + migración de sync | modificar |
| Layout / top menu | `frontend/components/Layout/MainLayoutPageBuilder.php`, `frontend/views/layouts/main.php` | modificar sin lógica de rol |
| Providers | `Domain/<BC>/Home/Sections/` (o `<Modulo>/Application/Presentation/` si el BC no admite `Home/`) + `product-registries.php` | nuevo |

## Rol operativo y cambios de contexto

El usuario elige servicio (y encounter type) al iniciar sesión, y puede cambiarlos desde el top menu. Ambos son variables de sesión y definen el panel: al cambiar cualquiera de los dos, cambia el home completo.

- `OperativeRoleResolver::resolve()`: el servicio en sesión manda; los roles RBAC sólo se usan cuando todavía no hay servicio elegido, con el orden de `precedence` como valor por defecto.
- `resolveFor(?string $serviceItemName, array $rbacRoleNames)` es la parte pura de esa decisión (testeable sin base).
- `resolveAll()` devuelve todas las claves presentes ordenadas por precedencia: sólo diagnóstico y QA, no compone paneles.
- Si el rol resuelto no tiene overlay en el manifest, se sigue con el panel por encounter.

## RBAC y exposición de datos

- El manifest decide **composición**, no permisos.
- Cada provider nuevo debe respetar el alcance del rol que lo consume; la verificación de acceso se hace en la capa de dominio o con capabilities.
- Los CTAs siguen declarando `capability_id`; el 403 lo resuelve el backend.
- `panel.staff_clinical` debe cubrir todos los roles que puedan llamar el endpoint tras la fase 03.

## Contrato con clientes

- `GET /api/v1/home/panel` expone `role` y mantiene `audience`, `layout`, `title`, `encounter_class`, `sections`.
- Los paneles de todos los roles se muestran también en las apps: no hay filtro por `X-App-Client`.
- Sin requisito de retrocompatibilidad: si un `layout` o `kind` nuevo no lo conoce un cliente, ese cliente se actualiza en el mismo programa.
- Web y apps renderizan `layout` conocidos; agregar un `layout` obliga a tocar los dos clientes.

## Tests

| Test | Cubre |
|------|-------|
| `OperativeRoleResolverTest` (nuevo) | fuentes de rol, normalización, precedencia, guest, superadmin |
| `HomePanelManifestTest` (extender) | matriz rol × encounter, cada escalón del fallback, merge (`append`/`exclude`), overlays vacíos |
| `HomePanelAudienceResolverTest` (nuevo o extendido) | rol de gestión sin sesión operativa |
| `ClientContextMetadataTest` (extender) | nuevas claves de top menu |
| Tests de providers | forma de `data` por sección |

`HomePanelManifest` cachea el YAML en estático: usar `resetCacheForTests()` en `_after()` como ya hace el test existente.

## Alternativas descartadas

| Alternativa | Motivo del descarte |
|-------------|---------------------|
| Colgar `role_overlays` de `panels.staff.<encounter>` (plan original) | Deja inalcanzables los paneles de roles sin encounter |
| Un panel por rol sin eje encounter | Enfermería y administrativo trabajan por encounter; duplicaría AMB/EMER/IMP por rol |
| Condicionales de rol en `MainLayoutPageBuilder` / `main.php` | Rompe la regla de metadata; ya existe `ClientContextMetadata` |
| Crear `RoleResolverService` en `Platform/Ui/Home/Service` | El rol lo necesitan menú y API, no sólo el home |
| Un provider por métrica sin verificar la fuente | Deuda fantasma: secciones que nunca se pueden llenar |
| `deep_merge` genérico de YAML entre overlay y base | Impredecible; se prefieren claves explícitas de append/exclude |

## Pendientes de decidir

1. Definición funcional de «pendiente» para signos vitales y admisiones.
2. Roles de auditoría (`_x_efector_aditoria`) y `_sin_efector_CEAMM`: ¿entran al panel o quedan fuera en v1?
3. Etiqueta del menú lateral por rol.

Resuelto con producto: el rol sale del servicio elegido en la sesión (cambiable desde el top menu), no de un desempate entre roles de la cuenta; AdminEfector elegirá encounter type como el resto.
