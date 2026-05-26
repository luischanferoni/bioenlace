# Progreso — clean-legacy

Última actualización: 2026-05-26

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
| Sub-controllers internación clínicos (`InternacionDiagnostico*`, medicamento, práctica, enfermería) | controller | [x] | 410 + trait 03d; captura → timeline IMP |
| `InternacionAtencionesEnfermeriaController` | controller | [x] | 410 (03d) |
| `InternacionHcamaController` | controller | [x] | create/update/delete 410; historial en index |
| `EncuestaParchesMamariosController` | controller | [-] | `personas/view` |
| `AutofacturacionController`, `ReporteController` | controller | [-] | SUMAR / planillas |

---

## Fase 03 — Consulta: desacople guardia + huérfanos

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| `GuardiaOperacionService` → `encounter_id` | service | [x] | `GuardiaEncounterResolver` |
| Guardia / queue / summary → `Encounter::PARENT_*` | service | [x] | Sin `Consulta::` en guardia |
| `ConsultaIA`, `ConsultarValidaciones` | model | [x] | Huérfanos |
| Búsquedas oftalmología/receta/suministro sin uso | busqueda | [x] | 3 archivos |
| Modelo `Consulta` + `ConsultaBusqueda` | model | [x] | `@deprecated`; shim hasta drop BD |
| `ConsultaProcesamientoService` → solo FHIR | service | [x] | `guardar()` delega a `EncounterDocumentationService` |
| `EncuestaParchesMamarios` crea `Consulta` | controller | [x] | Fase 03b → `EncounterLifecycleService` |

---

## Fase 03b — Encounter parches + atenciones enfermería

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| `EncuestaParchesMamariosController` → `Encounter` | controller | [x] | Sin fila en `consultas` |
| `ConsultaAtencionesEnfermeria.encounter_id` + compat `id_consulta` | model | [x] | Post migración `m260520_100001` |
| Constantes `Encounter` en `Turno`, `PacienteController`, listado | varios | [x] | Sin cambiar `Consulta::find*` |

### Fase 03b — Pendiente (03c)

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| `PersonasAntecedente.id_consulta` → `encounter_id` | model + BD | [x] | `m260526_100002` + trait FK dinámica |
| `PacientesController` motivos vía `Encounter` | API | [x] | `EncounterAppointmentReasonLookupService` |
| `ConsultaProcesamientoService` sin `consultas` | service | [x] | Sin escrituras en tabla `consultas` |
| Autofacturación SUMAR sobre `Encounter` | controller + model | [x] | `AutofacturacionEncounterBusqueda` |
| `Referencia` + datos persona sin `consultas` | model | [x] | `legacy_id_consulta` / trait |
| `ReporteController` + planillas ministeriales | controller + views | [x] | `EncounterReporteBusqueda` |
| Drop tabla `consultas` + hijas | migration | [ ] | Código listo; aplicar `m260520_100002` tras auditoría |

### Fase 03c — Paso 8 (shim Consulta)

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| Dead code `ConsultaProcesamientoService::guardar()` | service | [x] | Solo delega a FHIR |
| `ConsultasConfiguracion` → constantes `Encounter` | model | [x] | Fix `ENCOUNTER_CLASS_EMER` |
| `SegNivelInternacionRepository` sin join `consultas` | repository | [x] | Join `encounter` |
| `DiagnosticoConsultaRepository` IMP + contexto Encounter | repository | [x] | `resolveConsultaContext()` |
| Pase previo turnos → `Encounter::findPasePrevioEncounter` | model | [x] | |
| AR `Consulta` / `ConsultaBusqueda` `@deprecated` | model | [x] | Eliminar post-drop BD |
| Callers odontología / balance / régimen / suministro sin join `consultas` | model | [x] | Join `encounter`; console motivos → `reason_text` |
| Vista `view_consulta_diagnostico` → FHIR | BD + repo | [x] | Vista dual: legacy `diagnostico_consultas` o `clinical_condition` |

### Fase 03d — Internación MVC clínico (producto: web = móvil)

| Ítem | Tipo | Estado | Notas |
|------|------|--------|-------|
| Docs producto `superficies-ui.md`, internacion, captura | docs | [x] | Inicio vs encounter vs flows |
| `InternacionController::actionView` sin agregación `Consulta` | controller | [x] | Ficha admin + enlace timeline |
| Ronda «Atender» → `PatientHistoriaUrl` IMP | view | [x] | |
| Sub-controllers clínicos → 410 | controller | [x] | `RetiredInternacionClinicalMvcTrait` |
| Vistas `internacion/v2/_view_*` | view | [x] | Eliminadas |
| `InternacionHcamaController` create/update/delete | controller | [x] | 410; API + view#cambio-cama |
| Intent `internacion.cambio-cama-flow` | assistant | [x] | |
| Mapa camas solo en inicio / asistente | varios | [ ] | Reducir MVC index |
| Flow `internacion.ingreso-flow` + API ingreso | assistant + API | [x] | `internacion/ingreso` web; create → redirect |
| Mapa camas: cama ocupada → timeline IMP | view | [x] | `_mapa_camas.php` |
| `SegNivelInternacion::getEncounters()` | model | [x] | `getAtenciones()` deprecated |
| Borrar vistas `internacion-diagnostico/*`, etc. | view | [x] | 5 carpetas eliminadas |

---

## Backlog completo (fases 03c+)

### Captura / consulta legacy

| Ítem | Prioridad | Estado |
|------|-----------|--------|
| Controllers Yii `consulta-*` (ya eliminados) | — | [x] Fase 12 previa |
| Vistas huérfanas `consulta-atenciones-enfermeria/*` | — | [x] Fase 02 |
| `AtencionesEnfermeriaController` (solo view + reporte) | Media | [-] Mantener hasta API reporte |
| `InternacionAtencionesEnfermeriaController` | — | [x] | 410 (03d) |
| `PacienteController::actionFormularioConsulta` | Mantener | [-] | Camino único captura; renombrar `id_consulta` → `encounter_id` |
| Modelo AR `Consulta` + tablas `consultas`, `consulta_*` | Alta | [ ] | `@deprecated` Paso 8; drop `m260520_100002` |
| `ConsultaAtencionesEnfermeria`, `ConsultaPracticas*`, etc. | Alta | [ ] |

### Internación — operativo (sin MVC clínico)

| Ítem | Prioridad | Estado |
|------|-----------|--------|
| `InternacionDiagnosticoController` | — | [x] | 410 (03d) |
| `InternacionMedicamentoController` | — | [x] | 410 (03d) |
| `InternacionPracticaController` | — | [x] | 410 (03d) |
| `views/internacion/v2/_view_*.php` | — | [x] | Eliminadas (03d) |
| `InternacionHcamaController` | — | [x] | API + flow cambio cama (03d paso 3) |
| `InternacionController` index/ronda/view admin | Baja | [-] | Panel hasta inicio unificado |

### Facturación / reportes / turnos

| Ítem | Prioridad | Estado |
|------|-----------|--------|
| `AutofacturacionController` + vistas | Media | [x] | Encounter + SUMAR |
| `ReporteController` + planillas | Media | [x] | Encounter + planillas 4/5/7/9/farmacia |
| `ReferenciasController` | Media | [x] | `$idc` = encounter id (alias URL) |
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

### Fase 03

- [ ] `POST .../clinical/emergency-guardia/iniciar-atencion` devuelve `encounter_id` (no `id_consulta`)
- [ ] Tablero guardia web: botón «Atender» con sesión EMER
- [ ] Sin referencias rotas a clases borradas (`ConsultaIA`, búsquedas oftalmología)

### Fase 03b

- [ ] Crear encuesta parches con peso/talla: fila en `encounter` + `atenciones_enfermeria.encounter_id`
- [ ] Antecedentes SNOMED vinculados al encounter (`personas_antecedentes.encounter_id`)
- [ ] Migración `m260526_100002_personas_antecedentes_encounter_id` en entorno con backup
- [ ] Listado pacientes / turnos: enlaces «Atender» siguen abriendo timeline
- [ ] `GET .../personas/{id}/historia-clinica`: motivos vía `Encounter` (sin `Consulta::findOne`)
- [ ] Agenda ambulatoria en `PacientesController`: `encounter_id` por turno (alias `id_consulta`)
