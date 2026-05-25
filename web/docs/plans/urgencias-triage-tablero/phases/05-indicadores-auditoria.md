# Fase 5 — Indicadores y auditoría de guardia

## Objetivo

Dar visibilidad a **dirección y calidad**: tiempos door-to-triage y door-to-doctor, volumen por nivel, cumplimiento SLA, trazabilidad de cambios de prioridad.

## Checklist implementación

- [x] Medianas desde eventos en `GuardiaIndicadoresService` (ingreso → triage → médico, día actual)
- [x] API `GET /api/v1/clinical/emergency-guardia/indicadores-resumen`
- [ ] API export CSV
- [x] Resumen en cabecera del tablero (inicio web EMER)
- [ ] Vista `guardia/indicadores` dedicada (no requerida si inicio basta)
- [ ] Re-triage con historial
- [ ] Job materialización diaria

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

- Actualizar [02-urgencias.md](../../his-completo/02-urgencias.md).
- Crear `producto/urgencias-guardia.md`.
- Eliminar `plans/urgencias-triage-tablero/`.
