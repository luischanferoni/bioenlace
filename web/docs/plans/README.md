# Planes en ejecución

Solo **planes largos activos** (multi-fase, varios PR). Cuando un plan termina, se elimina su carpeta aquí; lo operativo queda en dominios (`Turnos/`, `asistente/`, `dominio/`, `decisions/`).

## Planes activos

| Plan | Carpeta | Estado |
|------|---------|--------|
| Receta electrónica (AR) | [receta-electronica/](./receta-electronica/) | Fases 1–2 hechas; Fase 3 repositorio nacional pendiente |
| Resumen de atención (paciente) | [resumen-atencion-paciente/](./resumen-atencion-paciente/) | Fase 1 API + push (UI Fase 3 pendiente) |
| Recordatorios care plan | — | **Cerrado** — ver [care-plan-recordatorios-paciente.md](../dominio/flows/care-plan-recordatorios-paciente.md) |

Plan laboratorio FHIR **cerrado** — documentación en [laboratorio/](../laboratorio/README.md).

Para abrir un plan: crear `plans/<slug>/` según [design.md](./design.md) y registrar la fila anterior.

## Convenciones

- [overview.md](./overview.md)
- [design.md](./design.md)

## Fuera de `plans/`

| Necesidad | Dónde |
|-----------|--------|
| Decisiones cerradas | [decisions/](../decisions/README.md) |
| Flujos y contratos vigentes | [dominios en `web/docs/`](../README.md) |
| Código clínico | `common/components/Clinical/README.md` |
