# Fase 0 — Marco (denominación y FHIR)

## Objetivo

Cerrar vocabulario y límites antes de absorber el flow. Sin migraciones obligatorias.

## Denominación producto

| Término UI / docs | Significado |
|-------------------|-------------|
| **Solicitar Atención** | Intent `atencion.necesito-atencion` |
| **Control/Seguimiento** | Motivo raíz `seguimiento_cronico` |
| **Tratamiento** | CarePlan activo del paciente |
| **Diagnóstico / condición** | Condition activa o crónica |
| **Protocolo de cuidado** / **Control recomendado** | Plantilla aplicable (PlanDefinition-lite) |
| **Consulta clínica por mensaje** | Async (`SOLICITUD_ASYNC`) — se mantiene |

**Evitar:** CarePack, cohorte, “pack” en copy de este hub.

## Mapa FHIR ↔ Bioenlace

| Concepto | FHIR | Bioenlace hoy | Este programa |
|----------|------|---------------|---------------|
| Tratamiento | CarePlan | `care_plan` | Reutilizar |
| Diagnóstico | Condition | `clinical_condition` | Reutilizar como ancla |
| Protocolo (plantilla) | PlanDefinition | — | **Nuevo** (YAML lite → opcional BD) |
| Paso del protocolo | ActivityDefinition | — | Embebido en YAML del protocolo (v1) |
| Pedido / turno | ServiceRequest / Appointment | turnos / async | Acciones existentes |
| Vacunas debidas | ImmunizationRecommendation | SISA (consulta) | Acción de protocolo (fase 4); no HIS vacunas completo |
| Packs IA | — | CareCohort | **Fuera** |

## Decisiones a confirmar en esta fase

- [x] CarePack ≠ protocolo de control.
- [ ] UI copy final: “Protocolo” vs “Control recomendado” vs “Programa”.
- [ ] ¿Materializar siempre CarePlan al aceptar un protocolo preventivo, o solo draft efímero en v1?
- [ ] ¿Consulta general y seguimiento de consulta previa siguen accesibles desde el hub o solo desde otras entradas?

**Default propuesto si no se discute:** copy **“Control recomendado”**; draft efímero en v1 (sin crear CarePlan preventivo automático); consulta general / consulta previa como secciones secundarias del hub o deep-links, no intents separados.

## Checklist

- [ ] Equipo alineado con esta tabla.
- [ ] No empezar Fase 3 con nombres CarePack.
