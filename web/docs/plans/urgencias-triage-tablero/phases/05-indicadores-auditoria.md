# Fase 5 — Indicadores y auditoría de guardia

## Objetivo

Dar visibilidad a **dirección y calidad**: tiempos door-to-triage y door-to-doctor, volumen por nivel, cumplimiento SLA, trazabilidad de cambios de prioridad.

## Checklist implementación

- [x] Medianas desde eventos en `GuardiaIndicadoresService` (ingreso → triage → médico, día actual)
- [x] API `GET /api/v1/clinical/emergency-guardia/indicadores-resumen`
- [x] API export CSV (`GET indicadores-export-csv`)
- [x] Resumen en cabecera del tablero (inicio web EMER)
- [ ] Vista `guardia/indicadores` dedicada (no requerida si inicio basta)
- [x] Re-triage con evento `re_triage` en `guardia_circuito_event`
- [x] Job materialización diaria (`guardia_metrics_daily`, `php yii emergency-guardia/materialize-metrics`)

## KPIs v1

| Indicador | Definición |
|-----------|------------|
| Tiempo a triage | `triage.at` − `ingreso.at` (mediana, p90) |
| Tiempo a médico | `inicio_atencion.at` − `ingreso.at` |
| Pacientes activos | Conteo por `circuito_estado` |
| % SLA incumplido | Por nivel vs umbral `efector_emergency_config` |
| Abandono / egreso sin atención | Egresos sin evento `inicio_atencion` |

## Criterio de aceptación

- Informe semanal exportable para efector piloto.
- Eventos inmutables; correcciones vía nuevo evento, no UPDATE silencioso.

## Cierre del programa

- [x] Actualizar [02-urgencias.md](../../his-completo/02-urgencias.md).
- [x] Crear [producto/urgencias-guardia.md](../../producto/urgencias-guardia.md).
- [ ] Eliminar `plans/urgencias-triage-tablero/` (cuando el equipo archive el plan).
