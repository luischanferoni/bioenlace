# Progreso — clean-legacy

Última actualización: 2026-05-20

Leyenda: `[x]` hecho · `[ ]` pendiente · `[-]` no aplica esta fase

---

## Fase 01 — Seguro + candidatos fuertes

### A. API y rutas

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| `ConsultaController` (API 410) | controller | [x] | Sustituto: `clinical/EncounterController` |
| Rutas `consulta/analizar`, `consulta/guardar` en `main.php` | config | [x] | |

### B. Vistas site huérfanas (pre–`site/pacientes`)

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| `views/site/_boxesHome.php` | view | [x] | Sin referencias |
| `views/site/_boxAtencion.php` | view | [x] | |
| `views/site/_boxGuardia.php` | view | [x] | |
| `views/site/_boxInternacion.php` | view | [x] | |
| `views/site/_boxBusquedaPersona.php` | view | [x] | Solo usada por `_boxesHome` |
| `views/site/_boxNovedades.php` | view | [x] | Solo usada por `_boxesHome` |
| Menú `Consultas` → `/consultas` en `layouts/produccion/main.php` | view | [x] | Ruta MVC inexistente |

### C. Demos / módulos aislados

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| `RecetaController` + `views/receta/*` | controller + views | [x] | Demo FHIR; receta real: `clinical/electronic-prescription/*` |
| `CovidEntrevistaTelefonicaController` + `views/covid-entrevista-telefonica/*` | controller + views | [x] | Quitar enlaces backend persona |
| Card Covid en `backend/views/personas/view.php` | view | [x] | |
| Card Covid en `frontend/views/personas/view.php` | view | [x] | |

### D. Guardia MVC (candidato fuerte)

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| `GuardiaController` + `views/guardia/*` | controller + views | [x] | Tablero: `site/pacientes` + API |
| Enlace `guardia/index` en `pacientes/_listado_templates.php` | view | [x] | Botón «Ingresos y libro» retirado |
| Modelo `Guardia` + tabla `guardia` | model + BD | [-] | **Mantener** — API activa |
| RBAC rutas web `guardia/*` (si existen) | rbac | [ ] | Auditar en fase 04 |

### E. Modelos / BD COVID

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| `CovidEntrevistaTelefonica` | model | [x] | Fase 02 |
| `CovidFactoresRiesgo` | model | [x] | |
| `CovidInvestigacionEpidemiologica` | model | [x] | |
| `CovidEntrevistaTelefonicaBusqueda` | busqueda | [x] | |
| Tablas `covid_entrevista_*` | BD | [x] | `m260605_100000_drop_covid_entrevista_tables` |

---

## Fase 02 — COVID + vistas huérfanas enfermería

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| Migración drop tablas COVID entrevista | migration | [x] | No incluye `infraestructura_sala.covid` |
| Vistas huérfanas `consulta-atenciones-enfermeria/*` (7 archivos) | view | [x] | Quedan view, reporte, _form_reporte |
| `AtencionesEnfermeriaBusqueda` (`ConsultaAtencionesEnfermeriaBusqueda`) | busqueda | [x] | Sin referencias |
| Modelo duplicado `AtencionesEnfermeria` | model | [x] | Controller → `ConsultaAtencionesEnfermeria` |
| Menú `/atenciones-enfermeria/index` (roto) | view | [x] | Solo reporte mensual |
| Backend persona `atenciones-enfermeria/create` (roto) | view | [x] | Queda link a view histórico |

### Fase 02 — Diferido (aún en uso)

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| Sub-controllers internación (`InternacionDiagnostico*`, etc.) | controller | [-] | `internacion/v2/_view_*` |
| `InternacionAtencionesEnfermeriaController` | controller | [-] | Flujo activo |
| `EncuestaParchesMamariosController` | controller | [-] | `personas/view` |
| `AutofacturacionController`, `ReporteController` | controller | [-] | SUMAR / planillas |

---

## Backlog completo (fases 03+)

### Captura / consulta legacy

| Ítem | Prioridad | Estado |
|------|-----------|--------|
| Controllers Yii `consulta-*` (ya eliminados) | — | [x] Fase 12 previa |
| Vistas huérfanas `consulta-atenciones-enfermeria/*` | — | [x] Fase 02 |
| `AtencionesEnfermeriaController` (solo view + reporte) | Media | [-] Mantener hasta API reporte |
| `InternacionAtencionesEnfermeriaController` | Alta | [ ] |
| `EncuestaParchesMamariosController` (crea `Consulta`) | Media | [ ] |
| `PacienteController::actionFormularioConsulta` | Mantener | [-] Renombrar `id_consulta` → `encounter_id` |
| Modelo AR `Consulta` + tablas `consultas`, `consulta_*` | Alta | [ ] |
| `ConsultaAtencionesEnfermeria`, `ConsultaPracticas*`, etc. | Alta | [ ] |

### Internación MVC por pestaña

| Ítem | Prioridad | Estado |
|------|-----------|--------|
| `InternacionDiagnosticoController` | Media | [ ] Bridge FHIR existe |
| `InternacionMedicamentoController` | Media | [ ] |
| `InternacionPracticaController` | Media | [ ] |
| `InternacionHcamaController` | Media | [ ] |
| `views/internacion/v2/_view_*.php` (datos legacy) | Media | [ ] |

### Facturación / reportes / turnos

| Ítem | Prioridad | Estado |
|------|-----------|--------|
| `AutofacturacionController` + vistas | Media | [ ] |
| `ReporteController` + planillas | Media | [ ] |
| `ReferenciasController` | Media | [ ] |
| `TurnosController` vistas `index2`, `espera2`, `show-calendar` | Baja | [ ] Auditar rutas |
| `NomencladorController` (refs `Consulta`) | Baja | [ ] |

### Otros

| Ítem | Prioridad | Estado |
|------|-----------|--------|
| `FormController` (forms externos) | Baja | [ ] |
| `MensajesController`, `Enviados`, `Recibidos` | Baja | [ ] |
| `EventsController` | Baja | [ ] |

---

## Verificación

### Fase 01

- [ ] `composer dump-autoload` / smoke web login + `site/pacientes` EMER
- [ ] `GET /api/v1/pacientes` con JWT staff
- [ ] Asistente ingreso guardia (intent / UI JSON) sin rutas `guardia/*`

### Fase 02

- [ ] `php yii migrate --migrationPath=@common/migrations` (drop COVID) en entorno con backup
- [ ] Persona backend/frontend: «Ver Atenciones de Enfermería» abre modal histórico
- [ ] Menú Enfermería → reporte mensual genera PDF/planilla
