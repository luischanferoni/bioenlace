# Fase 03d — Retiro MVC clínico internación

## Contexto de producto

Web staff = app médico: **inicio** (tableros), **captura encounter** (timeline + formulario), **flows** (asistente). Documentación: [superficies-ui.md](../../producto/superficies-ui.md), [internacion.md](../../producto/internacion.md).

Internación IMP **no** tiene UI clínica propia por pestañas; la evolución en piso usa `PatientHistoriaUrl::captura(..., PARENT_INTERNACION, $idInternacion)`.

## Paso 1 — Shell episodio + 410 sub-controllers (hecho)

### `InternacionController::actionView`

- Eliminada agregación legacy vía `$model->atenciones` / `Consulta`.
- Botón **Atender** → timeline con contexto IMP.
- Card «Captura clínica del episodio» en lugar de partials `v2/_view_*`.

### Ronda

- **Atender** → `PatientHistoriaUrl::captura` (IMP).
- **Ver episodio** → `/internacion/view` (ficha administrativa).

### Sub-controllers clínicos → HTTP 410

Trait `RetiredInternacionClinicalMvcTrait` en:

- `InternacionDiagnosticoController`
- `InternacionMedicamentoController`
- `InternacionPracticaController`
- `InternacionAtencionesEnfermeriaController`

Vistas eliminadas: `frontend/views/internacion/v2/_view_*.php`.

## Paso 2 — Mapa + modelo + vistas huérfanas (hecho)

- `_mapa_camas.php`: cama **ocupada** → `PatientHistoriaUrl::captura` (IMP); libre → ingreso `internacion/create`.
- `SegNivelInternacion::getEncounters()`; `getAtenciones()` `@deprecated`.
- Eliminadas carpetas de vistas MVC clínicas retiradas:
  - `internacion-diagnostico/`, `internacion-medicamento/`, `internacion-practica/`
  - `internacion-atenciones-enfermeria/`, `internacion-suministro-medicamento/` (sin controller)

## Paso 3 — Cambio de cama API + flow (hecho)

- `InternacionCambioCamaService` + `GET|POST …/internacion/<id>/cambio-cama-formulario`.
- UI JSON `cambio-cama-formulario.json`, intent `internacion.cambio-cama-flow`.
- Web: `_cambio_cama_api.php` + widget JS en `/internacion/view#cambio-cama`.
- `InternacionHcamaController` create/update/delete → **410**.
- Migración RBAC `m260526_120001_api_internacion_cambio_cama_rbac`.

## Mantenido (operativo / pendiente flow)

| Ítem | Motivo |
|------|--------|
| `InternacionController` index, create, ronda, view admin | Panel / ingreso / ficha episodio |
| `InternacionHcamaController` | Cambio de cama hasta `internacion.cambio-cama-flow` |
| `InternacionEpicrisisPlantillaController` | ABM plantillas |
| API `clinical/internacion/*` | Fuente de verdad |

## Pendiente (03d+)

| Ítem | Destino |
|------|---------|
| Mapa de camas web | Absorber en inicio / intent (reducir `/internacion/index` MVC) |
| `InternacionHcamaController` | Flow `internacion.cambio-cama-flow` + API | [x] Paso 3; MVC create → 410 |
| `internacion/create` ingreso | Flow `internacion.ingreso-flow` | [x] |
| Borrar vistas huérfanas `internacion-*` (diagnóstico, medicamento, …) | [x] Paso 2 |
| RBAC rutas web `internacion-diagnostico/*`, etc. | Fase 04 |
| `SegNivelInternacion::getConsultas()` / tablas hijas legacy | Tras drop `consultas` (03c) |

## Verificación

- [ ] Ronda: Atender abre timeline con formulario IMP.
- [ ] `/internacion/view`: sin pestañas clínicas; enlace a historia.
- [ ] `GET /internacion-diagnostico/create` → 410 con mensaje claro.
- [ ] Alta estructurada (flow / modal API) sigue funcionando.

## Revisión fases «limpias» anteriores

| Fase | Revisión |
|------|----------|
| **01** | OK — no tocaba internación clínica |
| **02** | Sub-controllers internación pasaron de «diferido activo» a **410** (03d) |
| **03 / 03b** | `PacienteController` + formulario = camino correcto; reafirmado en producto |
| **03c Paso 8** | Internación MVC listado como pendiente → cerrado parcialmente en 03d |
