# Migración RRHH → PES (`profesional_efector_servicio`): estado y guía de continuación

Documento operativo para **retomar el trabajo** sin perder el hilo: qué es PES, qué patrones usar en código, qué ya se alineó y qué suele quedar pendiente.

**Dominio detallado del modelo:** [PROFESIONAL_EFECTOR_SERVICIO.md](./PROFESIONAL_EFECTOR_SERVICIO.md)

---

## Objetivo de la migración

- Pasar de un mundo centrado en **`id_rr_hh` / `rrhh_efector` / `rrhh_servicio`** a uno donde la **asignación operativa canónica** es **`profesional_efector_servicio` (PES)**.
- Hacerlo **por módulos**, sin big-bang: conviven filas con solo RRHH, solo PES, o ambos, durante la transición.
- **API v1** y **Services** (`web/common/components/Services/…`) son la referencia de negocio; controladores web delgados.

---

## Principios prácticos

1. **Sesión operativa** puede traer `idRecursoHumano`, `idProfesionalEfectorServicio` (canónico), `servicio_actual`, `idEfector`. La clave `id_rrhh_servicio` en sesión/snapshot queda **reservada y en desuso** (típicamente `0`); los clientes nuevos deben usar `id_profesional_efector_servicio` / `idProfesionalEfectorServicio`.
2. **Filtros y reportes** que antes hacían `consultas.id_rr_hh = :x` deben considerar también **`id_profesional_efector_servicio`** cuando la consulta quedó registrada solo con PES (o resolver PES desde persona + efector + servicio a partir del `id_rr_hh` del formulario).
3. **Formularios web** que mandan `id_rr_hh` pero el Select2 en realidad usa **`rrhh_servicio.id`** (como en guardia): el campo persistido suele ser `id_rrhh_asignado` u homólogo; conviene **no inventar** `id_rr_hh` en el modelo si la tabla no lo tiene.
4. **Preferir helpers existentes** en `common/models/ProfesionalEfectorServicio.php`, por ejemplo:
   - `findIdByRrhhAndEfectorMinLegacyServicio`
   - `resolvePesIdFromGuardiaAsignado`
   - `findIdByPersonaEfectorServicio`
   - `resolveProfesionalEfectorServicioIdFromRrhhServicioId` (según caso)
5. **Consulta** ya sincroniza PES en `beforeSave` vía `syncProfesionalEfectorServicioFromContext()` cuando hay `id_rr_hh` + `id_efector` + `id_servicio` (o turno con PES).
6. **Bridges temporales PES→RRHH**: centralizar la resolución en un único helper para no duplicar lógica en controladores:
   - `web/common/components/Services/ProfesionalEfectorServicio/ProfesionalContextResolver.php`

---

## Migraciones Yii / SQL de referencia

- `web/common/migrations/m260508_000001_profesional_efector_servicio_model.php` — modelo PES + agenda + condición laboral (según versión actual del archivo).
- `web/common/migrations/m260508_000002_turnos_id_profesional_efector_servicio.php` — turnos.
- `web/common/migrations/m260508_000003_consumidores_id_profesional_efector_servicio.php` — consumidores (incl. `guardia`, `consultas`, etc.; ver comentarios en migración).
- `web/common/migrations/m260508_000004_consumidores_pes_lote2.php` — segundo lote.
- `web/common/migrations/m260508_000006_turnos_index_profesional_efector_servicio.php` — índices turnos.
- `web/common/migrations/m260509_000001_drop_rrhh_servicio_and_pes_legacy_bridge.php` — **retiro BD**: `DROP` de tabla `rrhh_servicio`, eliminación de `profesional_efector_servicio.legacy_rrhh_servicio_id` y normalización de `turnos.id_rrhh_servicio_asignado` donde hay PES (ver docblock de la migración; **requiere** diagnóstico previo y despliegue de código sin dependencia del AR `RrhhServicio`).
- SQL rutas Webvimark / permisos (si aplica en el entorno): `web/docs/sql/2026_migrate_webvimark_routes_profesional_agenda_recurso_humano.sql`

### Retiro de `rrhh_servicio` en base de datos

1. Ejecutar `web/docs/sql/diagnostico_pes_antes_eliminar_legacy.sql` hasta cumplir criterios del final del script.
2. Aplicar `yii migrate` incluyendo `m260509_000001_drop_rrhh_servicio_and_pes_legacy_bridge` (solo **mysql/mysqli**; otros drivers quedan omitidos con mensaje).
3. **Código posterior obligatorio:** quitar o desactivar usos de `\common\models\RrhhServicio`, métodos que lean `legacy_rrhh_servicio_id`, y cualquier SQL explícito a `rrhh_servicio`. Buscar: `rg "rrhh_servicio|legacy_rrhh_servicio_id|RrhhServicio" web/common web/frontend web/backend`.
4. **Fuera de esta migración:** columnas como `id_rrhh_servicio_asignado` (turnos), `id_rrhh_asignado` (guardia), `id_rrhh_servicio` en otros consumidores — siguen existiendo; un DDL futuro puede anularlas cuando el código deje de referenciarlas.

---

## Áreas ya alineadas o revisadas (resumen)

> Lista orientativa según el trabajo hecho en el repo; al continuar, verificar con `rg` en el módulo concreto.

| Área | Qué se buscó / patrón |
|------|------------------------|
| **Asistente / SubIntentEngine** | Intents YAML y flows pasan `id_profesional_efector_servicio` donde corresponde; hidratación de drafts (servicios alta PES, hydrators). |
| **API turnos / agenda** | `TurnosController`, `ProfesionalAgendaController`, servicios `ProfesionalEfectorServicioAgenda*`: aceptar PES además de RRHH legacy; asserts de efector/PES. |
| **Contrato slots (paciente/staff)** | `turnos/slots-disponibles-como-paciente`: `slot_id` PES-first (`pes:<id>|fecha|hora`) con compat legacy; modo `raw=1` devuelve `por_dia` + `available_filters` para widgets/autocomplete. |
| **Sesión** | `SiteController` / `SesionOperativaService`: fijar PES cuando solo había RRHH; documentación de endpoint operativo. |
| **Consulta (acceso y motivos)** | `ConsultaAccessService`; controladores API/web de motivos y chat; `Consulta::resolveIdRrhhParaMotivos()`; `ConsultaTrait::guardarConsulta` asigna PES desde sesión en altas. |
| **Web turnos** | `TurnosController::actionEventos`: request con PES, ocupación, etc. |
| **Paciente / turno hoy** | `Persona::turnoHoy`: además de servicio y `id_rrhh_servicio_asignado` vs RRHH en sesión, match por `id_profesional_efector_servicio` (sesión o parámetro). |
| **Reportes C4/C7** | `ConsultaBusqueda::searchParaReporteC4`: filtro por profesional ampliado (RRHH del form **o** PES alineado a persona/efector/servicio). Corrección en `searchReporteFarmacia` rama EMER (variable inexistente). Comentarios en `ReporteController`. |
| **Encuesta parches** | `EncuestaParchesMamariosController`: resolver `id_rr_hh` desde PES si falta RRHH en sesión; `EncuestaParchesMamarios::beforeSave` prefiere PES de sesión para `id_profesional_efector_servicio`; `SisseActionFilter` acepta contexto con RRHH **o** PES. |
| **Guardia** | `GuardiaController`: dejar de usar el atributo inexistente `id_rr_hh`; prellenar **`id_rrhh_asignado`** (`rrhh_servicio.id`) desde `id_rrhh_servicio` / RRHH / PES. `Guardia::beforeSave`: fallback PES desde sesión si el resolver por asignado no devuelve id. |
| **PersonaPrograma** | `PersonaProgramaController`: no depender de `idRecursoHumano` como único input; resolver RRHH desde sesión o PES cuando no venga parámetro. |
| **Web listado día / consultas del médico** | `ConsultasController::actionListadoSumar`: turnos del día filtrados por profesional vía **EXISTS rr_hh por persona** **o** fila PES (`profesional_efector_servicio`) alineada a persona + efector (sin cadena obligatoria `personas→rr_hh→turnos` únicamente). Docblock de clase del controller referencia PES + `ConsultaBusqueda::searchGral`. |
| **`ConsultaBusqueda::searchGral`** | Filtro “mis consultas” (`personas.id_user`) extendido con **OR** `turnos.id_profesional_efector_servicio` ∈ PES de la persona en sesión (`condicionTurnoAsignadoProfesionalSesion`). |
| **`ReferenciasBusquedas::search` (rol Médico)** | `LEFT JOIN` hacia `rr_hh`/`personas` y condición **OR** turno por `id_rr_hh` subquery **o** `id_profesional_efector_servicio` en PES del usuario. |
| **Paciente timeline (web)** | `PacienteController::actionHistoria`: solo renderiza timeline; historial vía API. Listados ambulatorios PES en `PacientesController::turnosAmbulatorioMedico`. |

### Cierre de fases (solo código, sin migraciones SQL)

| Fase | Cambios principales |
|------|---------------------|
| **Turnos / slots** | API `TurnosController`: ocupación con `id_efector` antes del resolver; reprogramación legacy sincroniza `id_rr_hh` vía PES/`legacy_rrhh_servicio_id` antes de tocar `RrhhServicio`. Web `TurnosController::actionEventos`: resolver slot con `ProfesionalEfectorServicio::resolverIdRrhhServicioDesdeRrhhServicioYEfector`. `SobreturnoService`: turnos colindantes por PES u homólogos legacy. |
| **Agenda PES** | `ProfesionalEfectorServicioAgendaUiService`: alta/carga sin exigir fila en `rrhh_servicio` (validación por PES persona+efector+servicio). `ProfesionalEfectorServicioAgendaApiService`: asserts con `exists` / PES+legacy; `obtenerOCrearPesParaRrhhServicioEnEfector` intenta PES por `legacy_rrhh_servicio_id` antes de leer legacy. `ProfesionalEfectorServicioAltaService`: persiste PES antes que `RrhhServicio` en la transacción. |
| **Guardia** | `GuardiaController::prefillIdRrhhAsignadoDesdeSesion`: RRHH con servicio en sesión vía resolver PES-first; con PES en sesión asigna `id_profesional_efector_servicio` y compat opcional en `id_rrhh_asignado`. |
| **Búsqueda turnos web** | `TurnoBusqueda`: atributo `profesional_clave` (`p<id>` PES o id legacy) con OR a `id_profesional_efector_servicio`; `searchAllTurnos` tolera RRHH sin filas `rrhh_servicio` (servicios desde PES). Vista `turnos/list.php`: Select2 mezcla opciones legacy + `ProfesionalEfectorServicio::opcionesProfesionalFiltroTurnosPorEfector`. |
| **UI / JS** | `UiScreenService`: `slot_id` PES-first. `agenda-laboral.js` / `spa-home.js` / `turnos_calendario.js`: identidad de slot y etiquetas desde `id_profesional_efector_servicio` y objeto `servicio` en slots; `id_rrhh_servicio_asignado` solo si no hay PES en sesión (calendario). |
| **Admin RRHH (backend)** | `RrhhEfectorController`: listado AdminEfector por EXISTS PES **o** `rrhh_servicio`; alta vía `ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector`; baja limpia PES + legacy. JSON create/remove alineados con PES. |
| **Remanente solo código (cierre tanda)** | Respuestas API turnos/listados con `id_profesional_efector_servicio`, `servicio_detalle` y `id_rrhh_servicio_asignado` (compat); `Turno::getServicioEmbebidoParaApi()`; wizard sesión y JWT con `id_profesional_efector_servicio` en nodo `servicio`; snapshot pestaña con `id_profesional_efector_servicio`; `TurnoBusqueda` numérico interpreta PK PES antes que legacy; `RrhhServicio` AR documentado como legacy; Flutter médico/paciente PES-first. |

---

## Auditoría rápida de controladores web (`id_rr_hh` / `getIdRecursoHumano`)

Archivos con ocurrencias (revisar según prioridad clínica):

`AutofacturacionController`, `ConsultaAtencionesEnfermeriaController`, `ConsultasController`, `EncuestaParchesMamariosController`, `GuardiaController`, `PacienteController`, `PersonaProgramaController`, `PersonasController`, `ReporteController`, `Rrhh_efectoresController`, `RrhhController`, `RrhhEfectoresController`, `ServiciosEfectoresController`, `SiteController`, `traits/ConsultaTrait`, `TurnosController`.

---

## Barrido deuda documentada (controladores web)

Revisión con `rg "id_rr_hh|getIdRecursoHumano" web/frontend/controllers` (sin contar nombres de parámetro/ruta CRUD):

| Controlador / módulo | Resultado |
|----------------------|-----------|
| **`ConsultasController`** | `actionListadoSumar` ya filtra turnos con EXISTS `rr_hh` **o** PES en turno. `actionIndex` → `ConsultaBusqueda::searchGral` (PES en “mis consultas”). Sin otras acciones con SQL solo-RRHH. |
| **`AutofacturacionController`** | Si no hay `getIdRecursoHumano`, resuelve `id_rr_hh` desde PES en sesión + `RrhhEfector`. |
| **`ConsultaAtencionesEnfermeriaController`** | Alta: setea `id_profesional_efector_servicio` y completa `id_rr_hh` desde PES cuando hace falta. |
| **`PersonaProgramaController`** | `resolveRrhhEfectorFromSessionOrPes()` para alta programa cuando no viene RRHH en request. |
| **`PacienteController`** | Sin SQL de agenda en acciones activas; timeline por API. |
| **`Rrhh_efectoresController` / `RrhhEfectoresController`** | Rutas `id_rr_hh` son la **PK del vínculo** `RrhhEfector` (no un filtro de listado clínico); no requieren el mismo patrón OR-PES que consultas/turnos. Evolución de UI/selects: plan de PRs aparte. |
| **`ConsultaBusqueda::searchConsultasPersona`** | Filtra por paciente (`id_persona`); no aplica filtro por profesional. |
| **Flutter médico `ConfigService`** | Trazas de depuración con `dart:developer` (`developer.log`, nombre `ConfigService`); no se vuelcan valores de `Authorization` en log. |

**Pendiente habitual** (fuera de este barrido, o trabajo de producto):

1. **CRUD RRHH web** — alinear selects y copy de pantalla con PES donde el producto lo pida (no bloqueado por filtros SQL pendientes en los listados anteriores).
2. **Cualquier nueva query** que filtre `consultas.id_rr_hh = …` sin OR PES — seguir el checklist más abajo.

Comando útil para auditar en frontend:

```bash
rg "id_rr_hh|getIdRecursoHumano" web/frontend/controllers --glob "*.php"
```

Y en busquedas / modelos:

```bash
rg "id_rr_hh|id_rrhh_asignado" web/common/models --glob "*Busqueda*"
```

---

## Plan sugerido de PRs (CRUD RRHH y satélites)

Separar en PRs pequeños para revisión:

1. **RRHH ↔ efector (web)** — `Rrhh_efectoresController` / `RrhhEfectoresController`: alinear selects con `rrhh_servicio` / PES según columna persistida; pruebas manuales de alta/edición.
2. **PersonaPrograma / Autofacturacion** — un PR por módulo: revisar solo rutas que filtren agenda o profesional por `id_rr_hh`.
3. **ConsultaAtencionesEnfermeria** — confirmar filtros de lista con OR PES si la consulta/enfermería guarda `id_profesional_efector_servicio`.

---

## Contrato JSON recomendado (turnos y sesión)

**Turno (listado / detalle / creación OK)**

- `id_profesional_efector_servicio` (number|null): identidad canónica del cupo profesional.
- `servicio_detalle` (object|null): `{ "id_servicio", "nombre" }` para UI sin depender de tablas legacy.
- `servicio` (string): nombre para display (se mantiene junto al objeto).
- `id_rrhh_servicio_asignado` (number): **solo compatibilidad**; puede ser `0` en filas PES puras.

**Slot ofrecido** (`TurnoSlotFinder` / UI lista)

- `id_profesional_efector_servicio`, `slot_id` tipo `pes:<id>|<fecha>|<hora>`, y `servicio`: `{ id_servicio, nombre }`.
- `id_rrhh_servicio_asignado`: compat; no usar como identidad principal.

**Sesión operativa** (`POST …/sesion-operativa/establecer`, wizard)

- En `servicio`: `id`, `nombre`, `id_profesional_efector_servicio`; `id_rrhh_servicio` reservado (0) / deprecated.

**Snapshot por pestaña** (`getPerTabSessions`)

- `id_profesional_efector_servicio` y `idProfesionalEfectorServicio` (mismo valor); `id_rrhh_servicio` legacy.

---

## Checklist al tocar un módulo nuevo

- [ ] ¿El formulario/envío usa `id_rr_hh`, `rrhh_servicio.id` o `id_profesional_efector_servicio`? Alinear el nombre del campo con la columna real.
- [ ] ¿La consulta SQL o ActiveQuery filtra solo `id_rr_hh`? Extender con OR PES o subconsulta por persona/efector/servicio.
- [ ] ¿Alta de entidad relacionada con `Consulta`? Confiar en `syncProfesionalEfectorServicioFromContext` o setear explícitamente PES solo si no entra en conflicto con el operador elegido.
- [ ] ¿Acceso condicionado a “tener RRHH”? Considerar **PES en sesión** como contexto válido (patrón `SisseActionFilter`).
- [ ] `php -l` en archivos PHP tocados; no duplicar lógica que ya vive en `ProfesionalEfectorServicio` o `Consulta`.

---

## Notas

- Este archivo es **estado de migración y guía**; el contrato de dominio PES sigue en [PROFESIONAL_EFECTOR_SERVICIO.md](./PROFESIONAL_EFECTOR_SERVICIO.md).
- Tras cada bloque de cambios, conviene **actualizar la tabla “Áreas ya alineadas”** y el **pendiente** para quien retome el hilo.
