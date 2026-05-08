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

1. **Sesión operativa** puede traer `idRecursoHumano`, `id_rrhh_servicio`, `idProfesionalEfectorServicio`, `servicio_actual`, `idEfector`. No asumir que todo está siempre lleno; sí usar lo que el flujo garantiza (ver reglas del proyecto en `.cursor/rules`).
2. **Filtros y reportes** que antes hacían `consultas.id_rr_hh = :x` deben considerar también **`id_profesional_efector_servicio`** cuando la consulta quedó registrada solo con PES (o resolver PES desde persona + efector + servicio a partir del `id_rr_hh` del formulario).
3. **Formularios web** que mandan `id_rr_hh` pero el Select2 en realidad usa **`rrhh_servicio.id`** (como en guardia): el campo persistido suele ser `id_rrhh_asignado` u homólogo; conviene **no inventar** `id_rr_hh` en el modelo si la tabla no lo tiene.
4. **Preferir helpers existentes** en `common/models/ProfesionalEfectorServicio.php`, por ejemplo:
   - `findIdByRrhhAndEfectorMinLegacyServicio`
   - `resolvePesIdFromGuardiaAsignado`
   - `findIdByPersonaEfectorServicio`
   - `resolveProfesionalEfectorServicioIdFromRrhhServicioId` (según caso)
5. **Consulta** ya sincroniza PES en `beforeSave` vía `syncProfesionalEfectorServicioFromContext()` cuando hay `id_rr_hh` + `id_efector` + `id_servicio` (o turno con PES).

---

## Migraciones Yii / SQL de referencia

- `web/common/migrations/m260508_000001_profesional_efector_servicio_model.php` — modelo PES + agenda + condición laboral (según versión actual del archivo).
- `web/common/migrations/m260508_000002_turnos_id_profesional_efector_servicio.php` — turnos.
- `web/common/migrations/m260508_000003_consumidores_id_profesional_efector_servicio.php` — consumidores (incl. `guardia`, `consultas`, etc.; ver comentarios en migración).
- `web/common/migrations/m260508_000004_consumidores_pes_lote2.php` — segundo lote.
- `web/common/migrations/m260508_000006_turnos_index_profesional_efector_servicio.php` — índices turnos.
- SQL rutas Webvimark / permisos (si aplica en el entorno): `web/docs/sql/2026_migrate_webvimark_routes_profesional_agenda_recurso_humano.sql`

---

## Áreas ya alineadas o revisadas (resumen)

> Lista orientativa según el trabajo hecho en el repo; al continuar, verificar con `rg` en el módulo concreto.

| Área | Qué se buscó / patrón |
|------|------------------------|
| **Asistente / SubIntentEngine** | Intents YAML y flows pasan `id_profesional_efector_servicio` donde corresponde; hidratación de drafts (servicios alta PES, hydrators). |
| **API turnos / agenda** | `TurnosController`, `ProfesionalAgendaController`, servicios `ProfesionalEfectorServicioAgenda*`: aceptar PES además de RRHH legacy; asserts de efector/PES. |
| **Sesión** | `SiteController` / `SesionOperativaService`: fijar PES cuando solo había RRHH; documentación de endpoint operativo. |
| **Consulta (acceso y motivos)** | `ConsultaAccessService`; controladores API/web de motivos y chat; `Consulta::resolveIdRrhhParaMotivos()`; `ConsultaTrait::guardarConsulta` asigna PES desde sesión en altas. |
| **Web turnos** | `TurnosController::actionEventos`: request con PES, ocupación, etc. |
| **Paciente / turno hoy** | `Persona::turnoHoy`: además de servicio y `id_rrhh_servicio_asignado` vs RRHH en sesión, match por `id_profesional_efector_servicio` (sesión o parámetro). |
| **Reportes C4/C7** | `ConsultaBusqueda::searchParaReporteC4`: filtro por profesional ampliado (RRHH del form **o** PES alineado a persona/efector/servicio). Corrección en `searchReporteFarmacia` rama EMER (variable inexistente). Comentarios en `ReporteController`. |
| **Encuesta parches** | `EncuestaParchesMamariosController`: resolver `id_rr_hh` desde PES si falta RRHH en sesión; `EncuestaParchesMamarios::beforeSave` prefiere PES de sesión para `id_profesional_efector_servicio`; `SisseActionFilter` acepta contexto con RRHH **o** PES. |
| **Guardia** | `GuardiaController`: dejar de usar el atributo inexistente `id_rr_hh`; prellenar **`id_rrhh_asignado`** (`rrhh_servicio.id`) desde `id_rrhh_servicio` / RRHH / PES. `Guardia::beforeSave`: fallback PES desde sesión si el resolver por asignado no devuelve id. |

---

## Pendiente habitual (siguientes pasos sugeridos)

Priorizar por **uso clínico** y por **consultas SQL** que aún filtren solo por `id_rr_hh`:

1. **`ConsultasController` (web)** — listados (`actionListadoSumar` y similares), uniones y filtros legacy.
2. **`PacienteController` / timeline** — proyecciones y unions que expongan `id_rr_hh` sin considerar PES donde la fila ya migró.
3. **CRUD RRHH web** (`Rrhh_efectoresController`, `RrhhEfectoresController`, …) — pantallas que aún modelan solo el esquema viejo.
4. **Otros controladores** con pocas líneas pero acoplamiento: `PersonaProgramaController`, `AutofacturacionController`, `ConsultaAtencionesEnfermeriaController`, etc. (revisar con búsqueda).

Comando útil para auditar en frontend:

```bash
rg "id_rr_hh|getIdRecursoHumano" web/frontend/controllers --glob "*.php"
```

Y en busquedas / modelos:

```bash
rg "id_rr_hh|id_rrhh_asignado" web/common/models --glob "*Busqueda*"
```

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
