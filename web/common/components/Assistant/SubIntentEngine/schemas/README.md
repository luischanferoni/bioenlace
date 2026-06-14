## Schemas YAML (SubIntentEngine)

Esta carpeta contiene la **fuente de verdad conversacional** del asistente **dentro de un intent**:

- YAML **por intent_id** (ej. `turnos.crear-como-paciente`)
- Opcionalmente referencias documentales a piezas compartidas (p. ej. `uses_global` apuntando a un id definido en `globals/`); la **fusión por imports no está implementada** en el motor.

**Contrato de claves por paso (`subintents`) y raíz (`flow_submit`):** [`SUBINTENT_CONTRACT.md`](SUBINTENT_CONTRACT.md) — incluye `open_ui`, `chooser`, `next_routing` y **`flow_submit`** a nivel intent (cierre GET+POST vía `action_id` del catálogo). No añadir propiedades nuevas en YAML sin actualizar ese contrato y `SubIntentEngine.php`.

Regla: el YAML no debe contener vocabulario clínico hardcodeado (ej. “oftalmólogo”) como reglas de keywords.

**`draft_hydrator`:** opcional en la raíz del intent; ver `SUBINTENT_CONTRACT.md`. Handlers registrados en `FlowDraftHydratorRegistry.php` (dominio en `Organization/Service/…`, etc.).  
La conversación se guía por:

- `requires` / `provides` (dependencias del draft)
- `open_ui` / `chooser` (mini-UIs por `action_id`)
- `flow_submit` en la raíz del YAML (cierre del flujo; ver contrato)

### Estructura

- Catálogo staff DataAccess: `Core/DataAccess/schemas/data-access-config/` (motor genérico; no vive bajo Assistant).
- `globals/` — ejemplos / contratos reutilizables para un futuro loader (p. ej. `location_acquisition.yaml`).
- `intents/` — manifiestos por intent_id:
  - `turnos.crear-como-paciente.yaml`
  - `turnos.cancelar-como-paciente-flow.yaml`
  - `turnos.modificar-como-paciente-flow.yaml`
  - `turnos.conflicto-agenda-flow.yaml`, `turnos.confirmar-asistencia-flow.yaml`, `turnos.consultar-politica-autogestion-flow.yaml`
  - `turnos.crear-para-paciente-flow.yaml`, `turnos.cancelar-para-paciente-flow.yaml`, `turnos.no-se-presento-flow.yaml`, `turnos.crear-sobreturno-flow.yaml`
  - `turnos.consultar-ocupacion-dia-flow.yaml`, `turnos.ver-agenda-dia-profesional-flow.yaml`
  - `profesional-efector-servicio.crear-flow.yaml`, `profesional-agenda.resolver-conflictos-flow.yaml`
  - `licencia.cargar-como-profesional-flow.yaml`, `licencia.cargar-para-profesional-flow.yaml`
  - Matriz intent ↔ API: `web/docs/Turnos/flows/intents-turnos.md`
