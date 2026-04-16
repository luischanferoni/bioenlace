## Schemas YAML (SubIntentEngine)

Esta carpeta contiene la **fuente de verdad conversacional** del asistente **dentro de un intent**:

- YAML **por intent_id** (ej. `turnos.crear-como-paciente`)
- YAML **globales/comunes** (ubicación, normalización temporal, política de confirmación, etc.)

Regla: el YAML no debe contener vocabulario clínico hardcodeado (ej. “oftalmólogo”) como reglas de keywords.  
La conversación se guía por:

- `requires/provides` (dependencias del draft)
- `views` embebibles (acciones UI por `action_id`, ej. `efectores.elegir`)
- `normalization` (globales)
- `confirmation_policy`

### Estructura

- `globals/` — piezas reutilizables:
  - `location_acquisition.yaml`
  - `temporal_normalization.yaml`
  - `confirmation_policy.yaml`

- `intents/` — manifiestos por intent_id:
  - `turnos.crear-como-paciente.yaml`
  - (otros intents conversacionales según el catálogo de acciones UI disponibles)

