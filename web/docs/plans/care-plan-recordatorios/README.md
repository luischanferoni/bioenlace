# Plan — Recordatorios de care plan (paciente)

Recordatorios de **tratamiento** derivados de care plans activos: agenda en API, preferencias y alarmas **locales** en Flutter. Sin FCM por toma.

## Estado

| Fase | Tema | Estado |
|------|------|--------|
| 0 | Marco y decisiones | Documentado |
| 1 | API: `dosage_json.timing` + schedule builder + endpoint | Implementado |
| 2 | Flutter: `flutter_local_notifications` + sync + switch global | Implementado |
| 3 | Preferencias por plan/ítem + horarios configurados por paciente | Implementado |
| 4 | Extensiones (service-request, sync prefs servidor, asistente) | Implementado |

## Documentos

- [overview.md](./overview.md) — alcance y actores
- [design.md](./design.md) — decisiones técnicas
- [phases/00-marco.md](./phases/00-marco.md)
- [phases/01-api-schedule.md](./phases/01-api-schedule.md)
- [phases/02-flutter-local.md](./phases/02-flutter-local.md)
- [phases/03-preferencias-paciente.md](./phases/03-preferencias-paciente.md)
- [phases/04-extensiones.md](./phases/04-extensiones.md)

## Doc operativa (al cerrar fases)

Al terminar Fase 2+, mover resumen estable a `web/docs/dominio/flows/care-plan-recordatorios-paciente.md` y borrar esta carpeta según [design.md](../design.md).

## PRs sugeridos

1. Fase 1 — API schedule + convención `dosage_json` + RBAC + tests manuales con seed care plan.
2. Fase 2 — Flutter local notifications + configuración master + sync al login/refresh.
3. Fase 3 — UI por plan/medicamento + horarios custom locales.
4. Fase 4 — Opcional según prioridad de producto.
