# Consultas y seguimiento (paciente)

> **Entrada de producto:** absorbida en **[Solicitar Atención](./solicitar-atencion.md)** → motivo **Control/Seguimiento**.  
> Este documento describe el **canal async** y las acciones sobre tratamiento que siguen existiendo detrás del hub.

## Denominación

| Término | Uso |
|---------|-----|
| **Consulta clínica por mensaje** | Nombre de producto: solicitud no urgente que un profesional **real** revisa y responde de forma asincrónica (sin turno ni videollamada). |
| **Consulta async** | Sinónimo técnico (`SOLICITUD_ASYNC`, encounter VR planificado, bandeja staff). |
| **Control/Seguimiento** | Motivo de Solicitar Atención que abre el hub (tratamientos, condiciones, protocolos, consulta general/previa). |

No confundir con «consulta rápida»: no promete respuesta inmediata. La IA puede clasificar y priorizar; la confirmación clínica la hace una persona.

## De qué se trata

Capacidades del paciente **sin mezclarlas** con malestar nuevo o urgencia:

- **Consulta general** — mensaje libre → **consulta clínica por mensaje**.
- **Seguimiento** de un **plan de tratamiento activo** — renovar medicación (multi-medicamento, sin texto libre), solicitar ajuste (medicamentos + motivo), consulta/evolución o pedir turno.
- **Seguimiento de una consulta previa** — mensaje vinculado a una atención ya publicada.
- **Condición / protocolo** — acciones derivadas del catálogo PlanDefinition-lite (turno o mensaje).

Sin plan activo u on-hold, el camino anclado solo a tratamiento muestra vacío o no ofrece esa ancla; el hub sigue ofreciendo consulta general, control general y (si aplica) protocolos de perfil.

**Canal:** solo la **app móvil paciente**. El personal opera la bandeja de consultas clínicas por mensaje y los turnos en web o app Personal de Salud.

## Separación de flujos

| Situación | Flujo |
|-----------|--------|
| Malestar nuevo, síntoma agudo, urgencia | [Solicitar Atención](./solicitar-atencion.md) → Malestar / Urgencia |
| Renovar o ajustar medicación, duda/evolución, control, consulta por mensaje | Solicitar Atención → **Control/Seguimiento** |
| Solo reservar o cancelar turno sin motivo clínico de seguimiento | Intents de turnos |

## Cómo funciona (tras el hub)

```mermaid
flowchart TD
  HUB[Hub Control/Seguimiento]
  HUB -->|CarePlan| NEC[Necesidad]
  HUB -->|condición / protocolo| ACC[Acciones protocolo o default]
  HUB -->|consulta general / previa| MSG[Mensaje libre]
  HUB -->|control general| TURN[Modalidad y turno]
  NEC -->|renovar_medicacion| MEDR[Multi-select medicamentos]
  MEDR --> CONFR[Confirmar sin texto]
  NEC -->|solicitar_ajuste| MEDA[Multi-select medicamentos]
  MEDA --> MOT[Motivo del ajuste]
  NEC -->|consulta o evolución| MSG
  NEC -->|turno| MOD[Preferencia profesional]
  MOD --> SLOTS[Slots y reserva]
  ACC -->|modalidad| TURN
  ACC -->|captura_mensaje| MSG
  CONFR --> ASYNC[Consulta clínica por mensaje]
  MOT --> ASYNC
  MSG --> ASYNC
```

1. **Hub** — ancla (tratamiento, condición, protocolo, extras, fallback).
2. **CarePlan** — si hay varios, elige uno (entrada desde el detalle del plan ya trae `care_plan_id`).
3. **Necesidad / acción** — renovar, ajustar, consulta/evolución, turno, u outcomes del protocolo.
4. **Medicación** — multi-selección de `MedicationRequest` del plan.
5. **Consulta async** — encounter VR planificado; el staff ve operación y medicamentos en bandeja/chat.

Metadata intake: `Scheduling/metadata/consultas_seguimiento_intake.yaml`. Hub: `control_seguimiento_hub.yaml`. API: `consultas-seguimiento/hub`, `condicion-acciones`, `paso`.

## Accesos en la app

- Atajo **Solicitar Atención** → **Control/Seguimiento**.
- **Detalle del plan de tratamiento** — acciones con plan (y a menudo necesidad) ya cargados → mismo intent `atencion.necesito-atencion`.
- Frases NL de renovación / seguimiento / consulta por mensaje → mismo intent.

## Relación con otros documentos

- [solicitar-atencion.md](./solicitar-atencion.md) — puerta y hub
- [planes-de-tratamiento.md](./planes-de-tratamiento.md)
- [atencion-remota-async.md](./atencion-remota-async.md)
- [triage-reserva-turno.md](./triage-reserva-turno.md)
- QA: [../qa/escenarios/seguimiento/README.md](../qa/escenarios/seguimiento/README.md)
