# Fase 2 — Hub Control/Seguimiento (paciente)

## Objetivo

Pantalla/paso único al entrar a Control/Seguimiento:

1. **Tratamientos activos** (CarePlan) — tap → mismas opciones que los botones del detalle de tratamiento.
2. **Diagnósticos activos/crónicos** (Condition) — tap → acciones del protocolo aplicable (Fase 3; mientras tanto placeholder o solo “pedir turno / consulta”).
3. **Control general** (opcional) — continuar a modalidad/turno sin ancla (fallback actual).

## UX (borrador)

```text
¿Sobre qué es el control o seguimiento?

[ Tratamiento: Hipertensión — desde 12/03 ]
[ Tratamiento: Diabetes tipo 2 — desde … ]

[ Condición: Asma (activa) ]
[ Condición: … ]

[ Pedir un control general ]
```

Al elegir un **tratamiento** → lista de acciones del catálogo intake (renovar, ajuste, consulta/evolución, turno, …).  
Al elegir una **condición** → acciones del protocolo (Fase 3).

## Implementación orientativa

| Pieza | Notas |
|-------|--------|
| `action_id` | p. ej. `atencion.control-seguimiento-hub` |
| Service | `ControlSeguimientoHubService` (Clinical o Scheduling): arma payload |
| CarePlan | Reusar `PatientActiveCarePlanQuery` + `accionesSeguimientoCarePlan()` |
| Condition | Query activos/crónicos del paciente (mismo criterio que historia) |
| Clientes | UI JSON web + Flutter genérico (listas + botones por `actions[]`) |

**0 hardcode:** las acciones salen del catálogo; el hub solo renderiza.

## Checklist

- [ ] API/ui_json devuelve secciones tipadas (`care_plans`, `conditions`, `fallback`).
- [ ] Acciones CarePlan = las del detalle (misma metadata).
- [ ] Sin CarePlans ni Conditions → fallback “control general” usable.
- [ ] Tests unitarios del armado de payload (fixtures).
- [ ] Flutter paciente + descriptor JSON.

## Dependencias

- Fase 1 (entrada unificada) hecha o en el mismo tren.
- Fase 3 para acciones ricas por Condition; hasta entonces Condition puede ofrecer 1–2 acciones fijas vía metadata provisional `condition_default_actions`.
