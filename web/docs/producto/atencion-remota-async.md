# Atención remota y consulta async

## De qué se trata

Bioenlace puede atender algunos motivos de consulta **sin que el paciente concurra presencialmente**: por **videollamada** (con turno reservado) o por **mensaje** (consulta async, sin turno ni video). La adopción es gradual: el personal médico sigue operando en presencial mientras el sistema educa y, más adelante, ofrece modalidades remotas al paciente y opt-in en la agenda del profesional.

## Actores

- **Paciente** — reserva o solicita atención vía asistente (`atencion.necesito-atencion`) con triage previo.
- **Profesional (PES)** — atiende turnos del día; puede habilitar remoto en su agenda (`acepta_consultas_online`).
- **Admin efector** — política de teleconsulta por servicio y métricas agregadas (etapas futuras).

## Cómo funciona (etapa 0 — observación staff)

Cuando un turno es **presencial** pero el triage persistido tiene elegibilidad **sugerida** o **permitida** para remoto, el listado **Pacientes del día** muestra un aviso informativo (videollamada y/o mensaje). Textos en `staff_modalidad_insight.yaml`; reglas clínicas vía `TeleconsultaElegibilidadService`.

## Cómo funciona (etapa 1 — oferta al paciente)

Tras el triage, el paciente puede ver el paso **Modalidad** con hasta tres opciones (catálogo `reserva_modalidad_atencion.yaml`):

- **Presencial** — siempre que el caso no sea de urgencia bloqueada.
- **Videollamada con turno** — si `TeleconsultaElegibilidadService` y la política del servicio lo permiten; slots vía hub teleconsulta sin elegir profesional.
- **Consulta por mensaje** — si la elegibilidad clínica es `sugerido` o `permitido`; crea un encounter virtual planificado (`SOLICITUD_ASYNC`) sin turno.

Si solo aplica presencial, el asistente **omite** el paso modalidad y fija `tipo_atencion=presencial`. Si no hay cupos de videollamada en el hub, la UI de días muestra un mensaje orientando a mensaje o presencial.

```mermaid
flowchart TD
  T[Triage] --> M{Más de una modalidad?}
  M -->|no| P[Turno presencial directo]
  M -->|sí| E[Elegir modalidad]
  E -->|presencial / teleconsulta| R[Reserva turno]
  E -->|async| S[Formulario mensaje → encounter VR]
```

## Etapas previstas

| Etapa | Foco |
|-------|------|
| 0 | Insight educativo en listado staff |
| 1 | Oferta modalidad al paciente + solicitud async mínima |
| 2 | Opt-in profesional: copy en agenda, KPI y link desde insight |
| 3 | Bandeja staff para async + chat operativo |
| 4 | Política y métricas por efector/servicio |

Plan de implementación: `web/docs/plans/atencion-remota-async/plan.md` (temporal).

## Cómo funciona (etapa 2 — opt-in profesional)

Al **configurar agenda**, el profesional ve un texto que distingue videollamada (switch opcional) y consulta por mensaje (no requiere el switch). El campo pasó a llamarse «Acepto videollamada en esta agenda».

En el listado del día, si la agenda no tiene remoto habilitado, el insight incluye enlace a **Configurar mi agenda** (asistente).

En los KPI de agenda (30 días), si hubo turnos presenciales con triage `sugerido`, aparece el indicador **Presencial (remoto posible)**.

## Relación con el resto

- [triage-reserva-turno.md](./triage-reserva-turno.md) — motivo y alarmas al reservar.
- [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md) — reglas de modalidad en reserva.
- [turnos.md](./turnos.md) — agenda y listado del día.
