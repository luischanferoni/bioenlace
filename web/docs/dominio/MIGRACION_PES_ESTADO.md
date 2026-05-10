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

1. **Sesión operativa** puede traer `idRecursoHumano`, `idProfesionalEfectorServicio` (canónico), `servicio_actual`, `idEfector`. Los clientes deben usar **`id_profesional_efector_servicio`** en requests y lógica; campos snapshot heredados (`id_rrhh_servicio`, etc.) no sustituyen al contrato API endurecido.
2. **Filtros y reportes** sobre consultas usan **`id_profesional_efector_servicio`** (columnas `consultas.id_rr_hh` retiradas por migración `m260512_*`). Los formularios deben enviar PK PES como **`id_profesional_efector_servicio`**.
3. **Formularios web**: valores de agenda/profesional deben ser **PK PES**; no existe tabla `rrhh_servicio` tras `m260509_000001`.
4. **Preferir helpers existentes** en `common/models/ProfesionalEfectorServicio.php`, por ejemplo:
   - `findFirstPesIdInEfector`
   - `resolvePesIdFromGuardiaAsignado`
   - `findIdByPersonaEfectorServicio`
   - `resolvePesIdFromPkEnEfector` (cuando el valor ya es PK PES en el efector)
5. **Consulta** sincroniza PES en `beforeSave` vía `syncProfesionalEfectorServicioFromContext()` cuando hay contexto efector/servicio y/o turno con PES (sin atributo `id_rr_hh` en AR).
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
- `web/common/migrations/m260510_000001_drop_agenda_rrhh_table.php` — **retiro BD**: `DROP TABLE agenda_rrhh` tras eliminar FKs entrantes (solo mysql/mysqli; ver docblock).
- `web/common/migrations/m260512_000001_drop_rrhh_legacy_columns_post_pes.php` — **retiro BD de columnas legacy** en tablas consumidoras (`consultas.id_rr_hh`, `turnos.id_rr_hh`, `guardia.*`, etc.), alineado a `web/docs/sql/retiro_legacy_rrhh_post_pes.sql`. Ejecutar en el mismo despliegue que el código AR sin esos atributos.
- `web/common/migrations/m260511_000001_drop_rrhh_efector_and_rrhh_laboral.php` — **retiro BD final (peligroso)**: `DROP TABLE rrhh_laboral` y `DROP TABLE rrhh_efector`. **Después** de `m260512_*` y sin modelos AR legacy (`RrhhEfector`/`RrhhLaboral` eliminados del código).
- SQL rutas Webvimark / permisos (si aplica en el entorno): `web/docs/sql/2026_migrate_webvimark_routes_profesional_agenda_recurso_humano.sql`

### Retiro de `rrhh_servicio` en base de datos

1. Ejecutar `web/docs/sql/diagnostico_pes_antes_eliminar_legacy.sql` hasta cumplir criterios del final del script.
2. Aplicar `yii migrate` incluyendo `m260509_000001_drop_rrhh_servicio_and_pes_legacy_bridge` (solo **mysql/mysqli**; otros drivers quedan omitidos con mensaje).
3. **Código posterior obligatorio:** quitar o desactivar usos de `\common\models\RrhhServicio`, métodos que lean `legacy_rrhh_servicio_id`, y cualquier SQL explícito a `rrhh_servicio`. Buscar: `rg "rrhh_servicio|legacy_rrhh_servicio_id|RrhhServicio" web/common web/frontend web/backend`.
4. **`m260509_000002_drop_legacy_rrhh_servicio_id_columns`** elimina columnas consumidoras (`turnos.id_rrhh_servicio_asignado`, `guardia.id_rrhh_asignado`, etc.); el código y la API usan **`id_profesional_efector_servicio`** sin alias de request `id_rrhh_servicio_asignado`.
5. **`m260510_000001_drop_agenda_rrhh_table`** elimina la tabla legado `agenda_rrhh` cuando el modelo canónico de agenda es `profesional_efector_servicio_agenda`.
6. **`m260512_000001_drop_rrhh_legacy_columns_post_pes`** elimina columnas `id_rr_hh` / `id_rrhh_*` legacy en tablas satélite (ver SQL de referencia).
7. **`m260511_000001_drop_rrhh_efector_and_rrhh_laboral`** cierra el esquema `rrhh_efector` / `rrhh_laboral` en BD. Referencia SQL comentada: `web/docs/sql/retiro_legacy_rrhh_post_pes.sql` (fase 3).

---

## Áreas ya alineadas o revisadas (resumen)

> Lista orientativa según el trabajo hecho en el repo; al continuar, verificar con `rg` en el módulo concreto.

| Área | Qué se buscó / patrón |
|------|------------------------|
| **Asistente / SubIntentEngine** | Intents YAML y flows pasan `id_profesional_efector_servicio` donde corresponde; hidratación de drafts (servicios alta PES, hydrators). |
| **API turnos / agenda** | `TurnosController` / `ProfesionalAgendaController`: **`id_profesional_efector_servicio`**; **`id_rr_hh`** como sinónimo en query/body donde aplique; `slots-disponibles-como-paciente` y ocupación del día resuelven PES desde `id_rr_hh` si hace falta; cancelación masiva del día acepta PES explícito o identificación vía `id_rr_hh`. |
| **Contrato slots (paciente/staff)** | `turnos/slots-disponibles-como-paciente`: `slot_id` (`pes:<id>|fecha|hora`); modo `raw=1` devuelve `por_dia` + `available_filters` para widgets/autocomplete. |
| **Sesión** | `SiteController` / `SesionOperativaService`: fijar PES cuando solo había RRHH; documentación de endpoint operativo. |
| **Consulta (acceso y motivos)** | `ConsultaAccessService`; controladores API/web de motivos y chat; `Consulta::resolveIdRrhhParaMotivos()`; `ConsultaTrait::guardarConsulta` asigna PES desde sesión en altas. |
| **Web turnos** | `TurnosController::actionEventos`: prioridad `id_profesional_efector_servicio`; si falta, `id_rr_hh` + servicio resuelven PES (`resolverIdPesDesdePersonaServicioYEfector`). |
| **Paciente / turno hoy** | `Persona::turnoHoy`: match por servicio, RRHH en sesión y/o **`id_profesional_efector_servicio`**. |
| **Reportes C4/C7** | `ConsultaBusqueda::searchParaReporteC4`: filtro por profesional ampliado (RRHH del form **o** PES alineado a persona/efector/servicio). Corrección en `searchReporteFarmacia` rama EMER (variable inexistente). Comentarios en `ReporteController`. |
| **Encuesta parches** | `EncuestaParchesMamariosController`: resolver `id_rr_hh` desde PES si falta RRHH en sesión; `EncuestaParchesMamarios::beforeSave` prefiere PES de sesión para `id_profesional_efector_servicio`; `SisseActionFilter` acepta contexto con RRHH **o** PES. |
| **Guardia** | `Guardia` persiste **`id_profesional_efector_servicio`**; vistas y API alineados a PES (columnas `id_rrhh_asignado` retiradas por `m260509_000002`). |
| **PersonaPrograma** | `PersonaProgramaController`: no depender de `idRecursoHumano` como único input; resolver RRHH desde sesión o PES cuando no venga parámetro. |
| **Web listado día / consultas del médico** | `ConsultasController::actionListadoSumar`: turnos del día filtrados por profesional vía **EXISTS rr_hh por persona** **o** fila PES (`profesional_efector_servicio`) alineada a persona + efector (sin cadena obligatoria `personas→rr_hh→turnos` únicamente). Docblock de clase del controller referencia PES + `ConsultaBusqueda::searchGral`. |
| **`ConsultaBusqueda::searchGral`** | Filtro “mis consultas” (`personas.id_user`) extendido con **OR** `turnos.id_profesional_efector_servicio` ∈ PES de la persona en sesión (`condicionTurnoAsignadoProfesionalSesion`). |
| **`ReferenciasBusquedas::search` (rol Médico)** | `LEFT JOIN` hacia `rr_hh`/`personas` y condición **OR** turno por `id_rr_hh` subquery **o** `id_profesional_efector_servicio` en PES del usuario. |
| **Paciente timeline (web)** | `PacienteController::actionHistoria`: solo renderiza timeline; historial vía API. Listados ambulatorios PES en `PacientesController::turnosAmbulatorioMedico`. |

### Cierre de fases (solo código, sin migraciones SQL)

| Fase | Cambios principales |
|------|---------------------|
| **Turnos / slots** | API y web: filtros y persistencia solo por **`id_profesional_efector_servicio`**. Sin tabla/columnas `rrhh_servicio` tras migraciones 260509. |
| **Agenda PES** | `ProfesionalEfectorServicioAgenda*`: validación por PES (persona+efector+servicio). `ProfesionalEfectorServicioAltaService` asegura filas PES. |
| **Guardia** | Solo **`id_profesional_efector_servicio`** en modelo y flujos actuales. |
| **Búsqueda turnos web** | `TurnoBusqueda` / vistas: opciones y filtros vía PES (`ProfesionalEfectorServicio::opcionesProfesionalFiltroTurnosPorEfector`, etc.). |
| **UI / JS** | Slots y calendario: identidad `id_profesional_efector_servicio` / `pes:…`. |
| **Admin RRHH (backend)** | Alta/baja alineadas con PES; sin dependencia de tabla `rrhh_servicio`. |
| **Cierre contrato** | API turnos/agenda: sin `id_rrhh_servicio_asignado` ni `id_agenda_rrhh` en JSON; JWT/snapshot pueden exponer campos adicionales de compatibilidad. Flutter PES-first. |

---

## Auditoría rápida de controladores web (`id_rr_hh` / `getIdRecursoHumano`)

Archivos con ocurrencias (revisar según prioridad clínica):

`AutofacturacionController`, `ConsultaAtencionesEnfermeriaController`, `ConsultasController`, `EncuestaParchesMamariosController`, `GuardiaController`, `PacienteController`, `PersonaProgramaController`, `PersonasController`, `ReporteController`, `ProfesionalEnEfectorController`, `RrhhController`, `ServiciosEfectoresController`, `SiteController`, `traits/ConsultaTrait`, `TurnosController`.

---

## Barrido deuda documentada (controladores web)

Revisión con `rg "id_rr_hh|getIdRecursoHumano" web/frontend/controllers` (sin contar nombres de parámetro/ruta CRUD):

| Controlador / módulo | Resultado |
|----------------------|-----------|
| **`ConsultasController`** | `actionListadoSumar` ya filtra turnos con EXISTS `rr_hh` **o** PES en turno. `actionIndex` → `ConsultaBusqueda::searchGral` (PES en “mis consultas”). Sin otras acciones con SQL solo-RRHH. |
| **`AutofacturacionController`** | Si no hay `getIdRecursoHumano`, resuelve `id_rr_hh` desde PES en sesión (`ProfesionalEfectorServicio`). |
| **`ConsultaAtencionesEnfermeriaController`** | Alta: setea `id_profesional_efector_servicio` desde sesión/contexto PES. |
| **`PersonaProgramaController`** | `resolveProfesionalEfectorServicioParaAlta()` para alta programa cuando no viene RRHH en request. |
| **`PacienteController`** | Sin SQL de agenda en acciones activas; timeline por API. |
| **`ProfesionalEnEfectorController`** | CRUD PES: **`id_profesional_efector_servicio`** + **`id_efector`**; también **`id_rr_hh`** (mismo valor PK PES). Redirects usan el nombre canónico. |
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

1. **RRHH ↔ efector (web)** — `ProfesionalEnEfectorController` + listado `RrhhController`: URLs y búsqueda por **`id_profesional_efector_servicio`** (hecho en app web); revisar grids/selects residuales y pruebas manuales.
2. **PersonaPrograma / Autofacturacion** — un PR por módulo: revisar rutas que aún lean **`id_rr_hh`** como nombre de parámetro y pasar a **`id_profesional_efector_servicio`** donde el producto lo permita (sesión PES ya disponible).
3. **ConsultaAtencionesEnfermeria** — confirmar filtros de lista con OR PES si la consulta/enfermería guarda solo `id_profesional_efector_servicio`.

---

## Contrato JSON recomendado (turnos y sesión)

**Turno (listado / detalle / creación OK)**

- `id_profesional_efector_servicio` (number|null): identidad canónica del cupo profesional.
- `id_rr_hh` (number|null): opcional; mismo valor que `id_profesional_efector_servicio` cuando se emite (p. ej. timeline médico en `PacientesController`). Preferir **`id_profesional_efector_servicio`** en clientes.
- `servicio_detalle` (object|null): `{ "id_servicio", "nombre" }` para UI sin joins extra.
- `servicio` (string): nombre para display (se mantiene junto al objeto).

**Slot ofrecido** (`TurnoSlotFinder` / UI lista)

- `id_profesional_efector_servicio`, `slot_id` tipo `pes:<id>|<fecha>|<hora>`, y `servicio`: `{ id_servicio, nombre }`.

**Agenda laboral (API `profesional-agenda`)**

- Ítem de listado/detalle: `id` = PK de `profesional_efector_servicio_agenda`; **`id_agenda_rrhh` no se expone**.
- Altas staff (`crear-para-recurso`, etc.): cuerpo/query con **`id_efector`** + **`id_profesional_efector_servicio`**; **`id_rr_hh`** aceptado con el mismo valor (prioridad en **`staffContextIdFromRequestParams`**).

**Sesión operativa** (`POST …/sesion-operativa/establecer`, wizard)

- En `servicio`: `id`, `nombre`, `id_profesional_efector_servicio`; `id_rrhh_servicio` reservado (0) / deprecated.

**Snapshot por pestaña** (`getPerTabSessions`)

- `id_profesional_efector_servicio` y `idProfesionalEfectorServicio` (mismo valor); `id_rrhh_servicio` como espejo numérico del PES cuando aplica.

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
