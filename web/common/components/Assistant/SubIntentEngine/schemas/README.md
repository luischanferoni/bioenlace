## Schemas YAML (SubIntentEngine)

Esta carpeta contiene la **fuente de verdad conversacional** del asistente **dentro de un intent**:

- YAML **por intent_id** (ej. `turnos.crear-como-paciente`)
- Opcionalmente referencias documentales a piezas compartidas (p. ej. `uses_global` apuntando a un id definido en `globals/`); la **fusión por imports no está implementada** en el motor.

Regla: el YAML no debe contener vocabulario clínico hardcodeado (ej. “oftalmólogo”) como reglas de keywords.  
La conversación se guía por:

- `requires/provides` (dependencias del draft)
- `views` embebibles (acciones UI por `action_id`, ej. `efectores.elegir`)

### Estructura

- `globals/` — ejemplos / contratos reutilizables para un futuro loader (p. ej. `location_acquisition.yaml`).
- `intents/` — manifiestos por intent_id:
  - `turnos.crear-como-paciente.yaml`
  - (otros intents conversacionales según el catálogo de acciones UI disponibles)
