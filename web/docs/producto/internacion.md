# Internación — producto

Gestión del **episodio de internación** en el efector: camas, indicadores operativos y **administración de plantillas** de epicrisis por institución. La fuente de verdad es la API v1 `clinical/internacion` y `clinical/internacion-epicrisis-plantilla`; web y app Personal de Salud consumen la misma capa.

Modelo de superficies (web = móvil): [superficies-ui.md](./superficies-ui.md).

## Roles y superficies

| Rol | Superficie | Comportamiento |
|-----|------------|----------------|
| Staff de piso / admisión | **Inicio** — mapa de camas (`/internacion/index` o intent `internacion.mapa-camas-flow`) | Libre, ocupada, bloqueada, aislamiento; indicadores. **No** decide el alta. |
| Médico / enfermería en piso | **Captura clínica** — timeline + formulario encounter | `parent=INTERNACION`, `parent_id=<id_internacion>`; workflow IMP por servicio del centro |
| Médico | **Captura del encounter** | Indica el **alta clínica** al documentar el diagnóstico (misma línea que [guardia](./urgencias-guardia.md)). El resto de roles no puede decidir el alta. |
| Staff | **Ficha episodio** — `/internacion/view` | Datos administrativos (cama, ingreso); enlace a historia clínica; **sin** pestañas clínicas MVC |
| Administración clínica | Web `/internacion-epicrisis-plantilla/*` | ABM plantillas epicrisis del efector |
| Médico (IMP) | app Personal de Salud — inicio con efector en sesión | Mapa de camas (misma API que web) |

Requiere **sesión operativa** con efector (staff). Paciente móvil sin `set-session` no usa estos flujos.

## Mapa de camas (inicio / panel)

Estados operativos en mapa (`estado_mapa`):

- `libre` — disponible  
- `ocupada` — paciente internado  
- `bloqueada` — fuera de servicio (con `motivo_estado` opcional)  
- `aislamiento` — reservada / aislamiento  

En web, acciones rápidas **B** / **A** / **L** (bloquear, aislamiento, liberar) vía API. Desde el **listado de internados** (modo recorrido piso→sala) o el mapa, **Atender** abre la **historia clínica** con contexto de internación (no el formulario MVC legacy). No hay CTA de alta en el tablero: el alta la indica el médico en la captura.

## Ronda (listado IMP)

No hay pantalla `/internacion/ronda`. La ronda es el listado del inicio IMP (`home/panel` → `inpatients`), con orden **por recorrido** (piso → sala → cama) o **por paciente** (A–Z). Misma API en web y móvil.

## Captura clínica en internación

- Mismo pipeline que ambulatorio/guardia: timeline + `_formulario_consulta.php` → `POST …/clinical/encounter/guardar`.
- Contexto: `PatientHistoriaUrl::captura($idPersona, Encounter::PARENT_INTERNACION, $idInternacion)`.
- Banner de episodio + evoluciones: [hcd-episodio-emergencia-internacion.md](./hcd-episodio-emergencia-internacion.md).
- Evoluciones, diagnósticos, medicación y prácticas del piso se documentan como **encounters IMP** vinculados al episodio (`parent_type` / `parent_id`), no en sub-controllers Yii retirados.
- Textos listos para pegar o dictar (`imp_standard`): [textos-ejemplo-captura-imp.md](../qa/escenarios/internacion/textos-ejemplo-captura-imp.md).

## Alta clínica (como guardia)

El médico **documenta el encounter** y, al establecer el diagnóstico de egreso, **indica el alta**. No hay intent de asistente ni segundo formulario en el tablero para que otro rol dé el alta.

Completar el care plan “Internación” **no** es el alta del episodio. El cierre de cama / `fecha_fin` (`doExternacion`) sigue en dominio; la ficha administrativa puede mostrar el formulario de externación de forma transitoria hasta cablear el outcome IMP al guardar la captura (equivalente a `GuardiaEncounterOutcomeService`).

Plantillas de epicrisis: ABM institucional (placeholders `{paciente}`, `{fecha_ingreso}`, `{dias_internacion}`, `{documento}`).

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
| Vista previa | ficha / API | `GET …/internacion/<id>/preview-plantilla-epicrisis` |

## API principal — internación operativa

Base: `/api/v1/clinical/internacion`

| Acción | Método | Notas |
|--------|--------|-------|
| Mapa de camas | `GET` (y `POST` para UI JSON asistente) | Filtros `id_piso`, `id_sala`; intent `internacion.mapa-camas-flow` |
| Indicadores | `GET indicadores-resumen` | Ocupación %, activas, estadía media/mediana |
| Marcar estado cama | `POST cama/<camaId>/marcar-estado` | `estado_mapa`, `motivo` opcional |
| Cambio de cama | `GET\|POST <internacionId>/cambio-cama-formulario` | UI JSON + submit; intent `internacion.cambio-cama-flow` |
| Alta formulario | `GET\|POST <internacionId>/alta-formulario` | Transitorio (ficha); no hay intent de asistente |
| Plantillas (uso) | `GET plantillas-epicrisis` | Solo activas para el efector |
| Preview plantilla | `GET <internacionId>/preview-plantilla-epicrisis` | Query `plantilla_id` |

## Vínculo con guardia

Ingreso desde urgencias: `internacion/create?id_guardia=` tras `POST …/emergency-guardia/<id>/solicitar-internacion`. Columna `seg_nivel_internacion.id_guardia` para trazabilidad. Candidato a flow `internacion.ingreso-flow`.

## Asistente

Intents YAML (UI JSON descubierta, sin hardcode de pantalla):

- `internacion.mapa-camas-flow` — mapa + listado embebible  
- `internacion.cambio-cama-flow` — traslado a otra cama  
- *(backlog)* `internacion.ingreso-flow`

No hay intent de alta: el egreso lo indica el médico en la captura.

## Retiro MVC clínico (clean-legacy)

**Eliminado / 410:** captura por pestañas (`InternacionDiagnostico*`, `InternacionMedicamento*`, `InternacionPractica*`, `InternacionAtencionesEnfermeria*`, partials `internacion/v2/_view_*`).

**Mantenido temporalmente:** `InternacionController` (index, view administrativo, create ingreso; `ronda` redirige al inicio), historial `InternacionHcamaController` (index), ABM plantillas. Cambio de cama vía API + `#cambio-cama` en view.

## Cobertura de plantel (agenda IMP)

El mapa de camas no es agenda de citas. La disponibilidad del personal de piso se declara como **cobertura** (`profesional_cobertura`, `encounter_class = IMP`). Ver [agenda-por-encounter-class.md](./agenda-por-encounter-class.md).

## Fuera de alcance actual

- Firma digital del responsable del alta  
- Integración quirófano–internación–facturación en un solo flujo  
- ABM de plantillas en app móvil (solo web + API hoy)

## Referencias

- HIS madurez: [his-completo/03-internacion.md](../his-completo/03-internacion.md)  
- Guardia e ingreso: [urgencias-guardia.md](./urgencias-guardia.md)  
- Captura clínica: [captura-clinica.md](./captura-clinica.md)  
- Motores asistente: [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md)
- Agenda tipada: [agenda-por-encounter-class.md](./agenda-por-encounter-class.md)
- QA por escenario: [../qa/escenarios/internacion/README.md](../qa/escenarios/internacion/README.md)
