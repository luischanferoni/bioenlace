# Fase 1 — Absorber Consultas y seguimiento

## Objetivo

Que **Control/Seguimiento** sea la única puerta; retirar el intent/atajo `atencion.consultas-seguimiento-flow` sin perder capacidades (renovar, ajuste, evolución, async, turno de control, consulta general / previa si se mantienen).

## Alcance

1. Tras `triage_raiz = seguimiento_cronico`, el flow **no** salta directo a modalidad genérica: entra al **hub** (Fase 2) o, como puente mínimo, al motor actual de consultas-seguimiento **inyectado** (mismo hydrator/intake) hasta que el hub exista.
2. Quitar `atencion.consultas-seguimiento-flow` de:
   - `assistant-shortcuts-paciente.yaml`
   - `assistant-shortcuts.yaml` (si aplica)
   - `client-context.yaml` / offering paciente
   - `intent-classification-rules.yaml` (redirigir scores/frases a `necesito-atencion` + contexto control)
3. Detalle CarePlan (`seguimientoAcciones`): deep-link a **Solicitar Atención** con draft prefilled (`triage_raiz`, `care_plan_id`, `seguimiento_necesidad`) en lugar del intent retirado.
4. Mantener YAML `consultas_seguimiento_intake.yaml` y servicios asociados; solo cambia la **entrada**.

## Puente recomendado (si Fase 2 no va en el mismo PR)

```text
necesito-atencion → Control/Seguimiento
  → open_ui / subintent que ejecuta la misma máquina de estados
    que hoy arranca consultas-seguimiento (select_tipo / care_plan / necesidad…)
```

Opciones técnicas (elegir una, sin hardcode de intent en orquestador):

- **A.** Fusionar subintents relevantes dentro de `atencion.necesito-atencion.yaml` (draft_hydrator compuesto o cadena).
- **B.** Handler de hub que, al elegir ancla+acción, llama al mismo `SubIntentEngine` con un `intent_id` interno aún existente pero **no listado** en shortcuts (deprecado).
- **C.** Extraer flow compartido a metadata reutilizable (ideal a medio plazo).

**Preferencia:** A o C en el PR limpio; B solo como tapajo temporal documentado.

## Checklist

- [x] Shortcut paciente ya no muestra “Consultas y seguimiento”.
- [x] Classification: frases de renovación/evolución/control → Solicitar Atención.
- [x] Desde detalle CarePlan, acciones abren Solicitar Atención con draft prefilled.
- [x] Tests de shortcuts / classification / flow YAML actualizados.
- [ ] Docs producto: nota temporal (Fase 5).

## Hecho en código (Fase 1)

- `atencion.necesito-atencion`: hydrator `scheduling.solicitar_atencion`; ruta `seguimiento_cronico` → `cs_select_tipo` + pasos absorbidos.
- Intent legacy `atencion.consultas-seguimiento-flow` marcado deprecated (sin atajos).
- Flutter: `PacienteIntents.solicitarAtencion` + draft `triage_raiz`/`intake_tipo`/`care_plan_id`/`seguimiento_necesidad`.

