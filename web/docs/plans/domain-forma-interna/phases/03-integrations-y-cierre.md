# Fase 3 — Integrations + cierre

## Hecho en esta pasada

| Sistema | Antes | Después |
|---------|--------|---------|
| `Mpi/` | PHP plano | `Mpi/Service/` |
| `Identity/DiditClient` | plano | `Identity/Connector/` |
| `Sisse/` | PHP plano | `Sisse/Service/` |
| `Scheduling/` raíz + `Util/` | helpers planos | → `Scheduling/Service/` (+ `FhirBundleHelper`) |
| README | — | `Domain/Integrations/README.md` (fase 0+) |

## Pendiente

- Cierre del plan: volcar residual a docs estables; borrar `plans/domain-forma-interna/` cuando el equipo lo dé por cerrado.

## Checklist

- [x] README corto en `Domain/Integrations/`
- [x] Sistemas planos obvios alineados (Mpi, Identity, Sisse, Scheduling helpers)
- [ ] Quitar fila del plan en `plans/README.md` y borrar la carpeta del plan (cierre explícito)
