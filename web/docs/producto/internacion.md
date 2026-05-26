# Internación — producto

Gestión del **episodio de internación** en el efector: camas, indicadores operativos, alta estructurada con epicrisis y **administración de plantillas** por institución. La fuente de verdad es la API v1 `clinical/internacion` y `clinical/internacion-epicrisis-plantilla`; web y app médico consumen la misma capa.

Modelo de superficies (web = móvil): [superficies-ui.md](./superficies-ui.md).

## Roles y superficies

| Rol | Superficie | Comportamiento |
|-----|------------|----------------|
| Staff de piso / admisión | **Inicio** — mapa de camas (`/internacion/index` o intent `internacion.mapa-camas-flow`) | Libre, ocupada, bloqueada, aislamiento; indicadores |
| Médico / enfermería en piso | **Captura clínica** — timeline + formulario encounter | `parent=INTERNACION`, `parent_id=<id_internacion>`; workflow IMP por servicio/especialidad |
| Médico / coordinación | **Flow** — alta estructurada (`internacion.alta-estructurada-flow`) | Epicrisis, plantilla, checklist → externación |
| Staff | **Ficha episodio** — `/internacion/view` | Datos administrativos (cama, ingreso); enlace a historia clínica; **sin** pestañas clínicas MVC |
| Administración clínica | Web `/internacion-epicrisis-plantilla/*` | ABM plantillas epicrisis del efector |
| Médico (IMP) | App médico — inicio con efector en sesión | Mapa de camas (misma API que web) |

Requiere **sesión operativa** con efector (staff). Paciente móvil sin `set-session` no usa estos flujos.

## Mapa de camas (inicio / panel)

Estados operativos en mapa (`estado_mapa`):

- `libre` — disponible  
- `ocupada` — paciente internado  
- `bloqueada` — fuera de servicio (con `motivo_estado` opcional)  
- `aislamiento` — reservada / aislamiento  

En web, acciones rápidas **B** / **A** / **L** (bloquear, aislamiento, liberar) vía API. Desde ronda/mapa, **Atender** abre la **historia clínica** con contexto de internación (no el formulario MVC legacy).

## Captura clínica en internación

- Mismo pipeline que ambulatorio/guardia: timeline + `_formulario_consulta.php` → `POST …/clinical/encounter/guardar`.
- Contexto: `PatientHistoriaUrl::captura($idPersona, Encounter::PARENT_INTERNACION, $idInternacion)`.
- Evoluciones, diagnósticos, medicación y prácticas del piso se documentan como **encounters IMP** vinculados al episodio (`parent_type` / `parent_id`), no en sub-controllers Yii retirados.

## Alta estructurada (flow)

Flujo guiado por UI JSON (`internacion/alta-formulario`):

1. Checklist y tipo de alta.  
2. Selección de **plantilla de epicrisis** (opcional) con vista previa.  
3. Epicrisis editable; responsable de sesión (PES) en checklist.  
4. Persistencia vía servicio de dominio → `doExternacion`.

**Placeholders** al aplicar plantilla: `{paciente}`, `{fecha_ingreso}`, `{dias_internacion}`, `{documento}`.

## Plantillas de epicrisis (ABM)

Tabla `internacion_epicrisis_plantilla`:

- Por **efector** (`id_efector`) o **global** (`id_efector = 0`, solo editable por superadmin).  
- Opcionalmente acotadas a un **servicio** del efector.  
- Baja lógica (`activo = 0`), orden de listado configurable.

| Acción | Web | API |
|--------|-----|-----|
| Listar (admin) | `/internacion-epicrisis-plantilla/index` | `GET …/internacion-epicrisis-plantilla/listar-admin` |
| Crear / editar | `/create`, `/update/<id>` | `POST …/crear`, `PUT/PATCH …/actualizar/<id>` |
| Activar / desactivar | POST en grilla | `POST …/activar/<id>`, `POST …/desactivar/<id>` |
| Listar (operativo, solo activas) | — | `GET …/internacion/plantillas-epicrisis` |
| Vista previa en alta | panel en flow / view | `GET …/internacion/<id>/preview-plantilla-epicrisis` |

## API principal — internación operativa

Base: `/api/v1/clinical/internacion`

| Acción | Método | Notas |
|--------|--------|-------|
| Mapa de camas | `GET` (y `POST` para UI JSON asistente) | Filtros `id_piso`, `id_sala`; intent `internacion.mapa-camas-flow` |
| Indicadores | `GET indicadores-resumen` | Ocupación %, activas, estadía media/mediana |
| Marcar estado cama | `POST cama/<camaId>/marcar-estado` | `estado_mapa`, `motivo` opcional |
| Alta formulario | `GET\|POST <internacionId>/alta-formulario` | UI JSON + submit; intent `internacion.alta-estructurada-flow` |
| Plantillas (uso) | `GET plantillas-epicrisis` | Solo activas para el efector |
| Preview plantilla | `GET <internacionId>/preview-plantilla-epicrisis` | Query `plantilla_id` |

## Vínculo con guardia

Ingreso desde urgencias: `internacion/create?id_guardia=` tras `POST …/emergency-guardia/<id>/solicitar-internacion`. Columna `seg_nivel_internacion.id_guardia` para trazabilidad. Candidato a flow `internacion.ingreso-flow`.

## Asistente

Intents YAML (UI JSON descubierta, sin hardcode de pantalla):

- `internacion.mapa-camas-flow` — mapa + listado embebible  
- `internacion.alta-estructurada-flow` — formulario de alta  
- *(backlog)* `internacion.cambio-cama-flow`, `internacion.ingreso-flow`

## Retiro MVC clínico (clean-legacy)

**Eliminado / 410:** captura por pestañas (`InternacionDiagnostico*`, `InternacionMedicamento*`, `InternacionPractica*`, `InternacionAtencionesEnfermeria*`, partials `internacion/v2/_view_*`).

**Mantenido temporalmente:** `InternacionController` (index, view administrativo, create ingreso, ronda), `InternacionHcamaController` (cambio de cama hasta flow), ABM plantillas.

## Fuera de alcance actual

- Firma digital del responsable del alta  
- Integración quirófano–internación–facturación en un solo flujo  
- ABM de plantillas en app móvil (solo web + API hoy)

## Referencias

- HIS madurez: [his-completo/03-internacion.md](../his-completo/03-internacion.md)  
- Guardia e ingreso: [urgencias-guardia.md](./urgencias-guardia.md)  
- Captura clínica: [captura-clinica.md](./captura-clinica.md)  
- Motores asistente: [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md)
