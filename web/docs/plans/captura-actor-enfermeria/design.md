# Design

## Definición (oferta del centro)

`encounter_definition`: una fila por `service_id` + `encounter_class` → `workflow_json` (`conf[]`: `titulo`, `relacion`, `requerido`).

Lookup al capturar: encounter vinculado o internación/guardia → `service_id` de esa oferta; si no, sesión. Bootstrap con `EncounterDefinitionWorkflowCatalog` (nombre del servicio + `item_name`).

`id_configuracion` en API/UI = `encounter_definition.id`. No es un alias de clase PHP; se mantiene el nombre de contrato.

## Actor (quién documenta)

No es columna de `encounter_definition`. Se toma del **PES de sesión** (`getServicioActual()` → `servicios.item_name`: `Medico`, `enfermeria`, …), no del `service_id` del episodio (clínica médica).

`EncounterCaptureCategoryResolver`:

1. Categorías base: `EncounterDefinition::getCategoriasParaPrompt`.
2. Overlay de actor (`EncounterCaptureActorCatalog`): puede **agregar** secciones (p. ej. signos vitales en IMP) y marcar `sugerido`.
3. Overlay CarePlan inpatient: actividades → `sugerido` + `plan_hints` (medicación, indicaciones, régimen).

Completitud: `requerido` vacío bloquea; `sugerido` vacío no. Filas extraídas siguen `*Input::rules()`. Persistencia recorre las categorías **resueltas** (incluye SV agregado al overlay).

## App Personal de Salud

`GET /home/panel` sección `emergency_board` incluye `puede_triage` (`GuardiaBoardCapabilityService` + `home-panel-manifest.yaml` `triage_roles`). Flutter abre `EmergencyTriageScreen` si es true; si no, mensaje para médico.

## Demo

Perfil `enfermeria` efímero: PES sobre servicio `ENFERMERIA`, RBAC `enfermeria`, sin agenda AMB, cobertura EMER+IMP para ver tablero.
