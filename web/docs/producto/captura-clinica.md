# Captura clínica

## De qué se trata

Durante la atención, el profesional registra la evolución por **texto** o **audio**. El sistema interpreta, corrige y enriquece con IA, y persiste el encuentro clínico (encounter FHIR).

La captura es **una sola superficie** para ambulatorio, guardia, internación y demás contextos: el formulario muta según encounter, rol y especialidad — igual en web y móvil. Ver [superficies-ui.md](./superficies-ui.md).

## Superficie web (shell)

| Pieza | Rol |
|-------|-----|
| `paciente/historia` (timeline) | Estado del paciente, historial, contexto |
| `_formulario_consulta.php` | Entrada texto/audio + análisis + confirmación |
| `PacienteController::actionFormularioConsulta` | Resuelve `id_configuracion` vía `EncounterDefinition` |

**Contexto del formulario** (hidden / query):

- `id_persona`
- `parent` + `parent_id` — turno, internación, guardia, etc. (`Encounter::PARENT_*`)
- `id_consulta` — id del encounter en curso (alias legacy; semántica = `encounter_id`)
- `id_configuracion` — fila de `encounter_definition` (servicio + `encounter_class` + workflow por especialidad)

Entrada desde listados: `PatientHistoriaUrl::captura($idPersona, $parent, $parentId)`.

## Cómo funciona

```mermaid
flowchart TB
  M[Médico dicta o escribe]
  UI[Timeline + formulario]
  API[API encounter analizar / guardar]
  DEF[EncounterDefinition workflow_json]
  IA[Servicios de texto e IA]
  ENC[Encounter FHIR]
  M --> UI
  UI --> DEF
  UI --> API
  API --> IA
  IA --> API
  API --> ENC
```

1. **Entrada:** audio transcrito o texto libre.
2. **Configuración:** `EncounterCaptureContextService::validarPermisoAtencion(parent, parent_id)` + lookup de `EncounterDefinition` → categorías/pasos del workflow.
3. **Análisis:** extracción de conceptos; lo no mapeado puede quedar para revisión.
4. **Guardado:** `EncounterDocumentationService` persiste FHIR; no escribe en tabla legacy `consultas`.

## Mutación por contexto

| Dimensión | Efecto |
|-----------|--------|
| `encounter_class` (AMB, EMER, IMP, …) | Clase FHIR y definición de workflow |
| Rol (médico, enfermería, …) | Permisos y visibilidad en timeline |
| Especialidad / servicio | `EncounterDefinition` y registries (oftalmología, odontología, …) |

## Niveles de carga

- Carga mínima: solo lo esencial para cerrar la atención.
- Carga ampliada: más campos estructurados cuando el servicio lo exige en el workflow.

## Relación con el paciente

El paciente **no** ve el dictado crudo ni el expediente legal completo; ve el **resumen en lenguaje claro** descrito en [resumen-atencion-paciente.md](./resumen-atencion-paciente.md).

## Conversación clínica

La captura puede iniciarse desde la conversación integrada o desde el timeline; arquitectura en [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md).

## Lo que no es captura clínica

- Tableros de inicio (guardia, mapa de camas, agenda).
- Flows operativos (alta de internación, cambio de cama).
- Vistas MVC legacy por pestaña (`internacion-diagnostico/*`, etc.) — retiradas; ver plan [clean-legacy](../plans/clean-legacy/README.md).
