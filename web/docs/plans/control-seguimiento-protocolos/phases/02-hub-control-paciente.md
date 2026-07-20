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

- [x] API/ui_json devuelve secciones tipadas (`care_plans`/`conditions`/`fallback` como ítems de lista con `meta.kind`).
- [x] Acciones CarePlan = las del detalle (misma metadata vía `cs_select_necesidad`).
- [x] Sin CarePlans ni Conditions → fallback “control general” + consulta mensaje/previa usable.
- [x] Tests unitarios del armado de payload (fixtures).
- [x] Descriptor JSON hub + condicion-acciones (Flutter consume lista genérica UiJsonScreen).

## Hecho en código (Fase 2)

- `ControlSeguimientoHubService` + metadata `control_seguimiento_hub.yaml`
- API `consultas-seguimiento/hub` y `condicion-acciones` + RBAC migration
- Flow: `seguimiento_cronico` → `cs_hub` → ramas por `control_hub_kind`

