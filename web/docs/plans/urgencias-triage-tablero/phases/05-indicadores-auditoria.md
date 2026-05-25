# Fase 5 — Indicadores y auditoría de guardia

## Objetivo

Dar visibilidad a **dirección y calidad**: tiempos door-to-triage y door-to-doctor, volumen por nivel, cumplimiento SLA, trazabilidad de cambios de prioridad.

## Checklist implementación

- [ ] `GuardiaTimingService`: calcular desde `guardia_circuito_event` (ingreso → triage → inicio_atencion → egreso)
- [ ] API `GET /api/v1/emergency/indicadores/resumen` (rango fechas, `id_efector`)
- [ ] API `GET /api/v1/emergency/indicadores/export` (CSV) — permiso restringido
- [ ] Vista web `guardia/indicadores` (gráficos simples: barras por nivel, mediana tiempos)
- [ ] Auditoría: quién cambió triage (historial si se permite re-triage con motivo)
- [ ] Job nocturno opcional: materializar métricas en tabla `guardia_metrics_daily` si el volumen lo requiere

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
