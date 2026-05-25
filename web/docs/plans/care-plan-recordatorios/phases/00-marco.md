# Fase 0 — Marco

## Objetivo

Alinear producto y arquitectura antes de código.

## Decisiones cerradas

- [x] Recordatorios **genéricos por care plan** (no solo un tipo de programa).
- [x] Paciente **opt-in** (desactivado por defecto o activación explícita — definir en Fase 2 UI; recomendado: **off** hasta que el usuario active).
- [x] **Locales en Flutter**, no FCM por toma.
- [x] API solo entrega **agenda derivada**; no cron servidor de medicación.

## Preguntas de producto (resolver antes de Fase 3)

| # | Pregunta | Propuesta default |
|---|----------|-------------------|
| 1 | ¿Default global on u off? | **Off** hasta primer toggle en Configuración |
| 2 | ¿Planes `on_hold` generan recordatorios? | **No** en v1 |
| 3 | ¿Anticipación minutos antes? | 0 en v1 (hora exacta); configurable en Fase 3 |
| 4 | ¿Snooze / “tomé”? | Fuera de alcance v1 |

## Checklist

- [x] Plan de planes en `web/docs/plans/care-plan-recordatorios/`
- [ ] Revisión con equipo clínico: formato `dosage_json.timing` al cargar medicación
- [x] Registrar plan en [README.md](../README.md) de `plans/`
