# Plan — Control/Seguimiento + protocolos de cuidado

| Campo | Valor |
|-------|--------|
| Slug | `control-seguimiento-protocolos` |
| Estado | En ejecución — Fase 4 pendiente (Fases 1–3 hechas) |
| Dueño | Equipo asistente / Scheduling / Clinical |
| Entrada | Motivo **Control/Seguimiento** en `atencion.necesito-atencion` (**Solicitar Atención**) |

## Índice

- [overview.md](./overview.md) — objetivo, actores, fuera de alcance
- [design.md](./design.md) — decisiones FHIR/producto, capas, PRs
- [phases/00-marco.md](./phases/00-marco.md) — denominación, mapa FHIR, no-CarePack
- [phases/01-absorber-consultas-seguimiento.md](./phases/01-absorber-consultas-seguimiento.md) — unificar intent bajo Control/Seguimiento
- [phases/02-hub-control-paciente.md](./phases/02-hub-control-paciente.md) — UI: tratamientos + condiciones
- [phases/03-protocolos-definitional.md](./phases/03-protocolos-definitional.md) — catálogo PlanDefinition-lite
- [phases/04-reglas-perfil-preventivo.md](./phases/04-reglas-perfil-preventivo.md) — edad/sexo/calendario (vacunas, controles)
- [phases/05-cierre-producto.md](./phases/05-cierre-producto.md) — docs estables y retiro del plan

## Punto de partida (código)

| Área | Ubicación |
|------|-----------|
| Solicitar Atención | `metadata/.../intents/create/atencion.necesito-atencion.yaml` |
| Triage raíz (Control/Seguimiento) | `Scheduling/metadata/reserva_triage_catalog_v1.yaml` → `seguimiento_cronico` |
| Intent a absorber | `atencion.consultas-seguimiento-flow.yaml` |
| Acciones tratamiento | `Scheduling/metadata/consultas_seguimiento_intake.yaml` |
| CarePlan paciente | `Clinical/CarePlan*`, UI `ver-tratamiento-paciente`, Flutter `care_plan_detail_screen` |
| Condition | `Clinical/Condition` (`clinical_condition`) |
| CarePack (no reutilizar como protocolo) | `Clinical/CareCohort/` |

## Relacionado (docs estables; no enlazar desde fuera hacia este plan)

- Al cerrar: volcar a `producto/solicitar-atencion.md` (o actualizar `consultas-seguimiento.md` + `triage-reserva-turno.md` + `planes-de-tratamiento.md`) y ADR corto en `decisions/` si se cierra mapeo PlanDefinition.
