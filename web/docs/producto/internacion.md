# Internación — producto

Gestión del **episodio de internación** en el efector: camas, indicadores operativos, alta estructurada con epicrisis y **administración de plantillas** por institución. La fuente de verdad es la API v1 `clinical/internacion` y `clinical/internacion-epicrisis-plantilla`; web y app médico consumen la misma capa.

## Roles y superficies

| Rol | Superficie | Comportamiento |
|-----|------------|----------------|
| Staff de piso / admisión | Web `/internacion/index` | Mapa de camas (libre, ocupada, bloqueada, aislamiento), indicadores de ocupación y estadía |
| Médico / coordinación | Web `/internacion/view` | Evolución del episodio; **alta estructurada** (API + modal clásico) con plantilla y checklist |
| Administración clínica | Web `/internacion-epicrisis-plantilla/*` | ABM de plantillas de epicrisis del efector |
| Médico (IMP) | App médico — icono cama en inicio | Mapa de camas del efector en sesión |

Requiere **sesión operativa** con efector (staff). Paciente móvil sin `set-session` no usa estos flujos.

## Mapa de camas

Estados operativos en mapa (`estado_mapa`):

- `libre` — disponible  
- `ocupada` — paciente internado  
- `bloqueada` — fuera de servicio (con `motivo_estado` opcional)  
- `aislamiento` — reservada / aislamiento  

En web, acciones rápidas **B** / **A** / **L** (bloquear, aislamiento, liberar) vía API.

## Alta estructurada

Flujo guiado por UI JSON (`internacion/alta-formulario`):

1. Checklist y tipo de alta.  
2. Selección de **plantilla de epicrisis** (opcional) con vista previa.  
3. Epicrisis editable; responsable de sesión (PES) en checklist.  
4. Persistencia vía servicio de dominio → `doExternacion` (flujo legacy integrado).

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
| Vista previa en alta | panel en `view` | `GET …/internacion/<id>/preview-plantilla-epicrisis` |

Enlace desde **Internaciones** → “Plantillas de epicrisis”.

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

Ingreso desde urgencias: `internacion/create?id_guardia=` tras `POST …/emergency-guardia/<id>/solicitar-internacion`. Columna `seg_nivel_internacion.id_guardia` para trazabilidad.

## Asistente

Intents YAML (UI JSON descubierta, sin hardcode de pantalla):

- `internacion.mapa-camas-flow` — mapa + listado embebible  
- `internacion.alta-estructurada-flow` — formulario de alta  

## Operación — migraciones (orden sugerido)

1. `m260604_100002_api_clinical_internacion_operativa_rbac`  
2. `m260604_100003_internacion_refinamiento` (tabla plantillas, `motivo_estado` en cama, seed globales)  
3. `m260604_100004_api_internacion_refinamiento_rbac`  
4. `m260604_100005_api_internacion_epicrisis_plantilla_abm_rbac`  

## Fuera de alcance actual

- Firma digital del responsable del alta  
- Integración quirófano–internación–facturación en un solo flujo  
- ABM de plantillas en app móvil (solo web + API hoy)

## Referencias

- HIS madurez: [his-completo/03-internacion.md](../his-completo/03-internacion.md)  
- Guardia e ingreso: [urgencias-guardia.md](./urgencias-guardia.md)  
- Motores asistente: [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md)  
- Código: `web/common/components/Inpatient/`, `InternacionController`, `InternacionEpicrisisPlantillaController` (API)
