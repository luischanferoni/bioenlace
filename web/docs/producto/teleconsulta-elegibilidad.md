# Elegibilidad de teleconsulta al reservar turno

## Objetivo

Decidir de forma **simple y honesta** si el paciente puede elegir **modalidad remota** (teleconsulta) o si el flujo fija **presencial** y omite el paso de elección.

No es diagnóstico: combina triage de reserva, política del **servicio del centro** y configuración de agenda del profesional (PES).

Ver [glosario-servicio-pes-acto.md](./glosario-servicio-pes-acto.md).

## Capas

| Capa | Responsabilidad |
|------|-----------------|
| Catálogo triage (`reserva_triage_catalog_v1.yaml`) | `teleconsulta_elegibilidad` por nodo (`excluido`, `presencial_preferido`, `permitido`, `sugerido`) y bandas A/B |
| Servicio (`servicios.teleconsulta_politica`) | `ninguna` (default), `todas`, `algunas` |
| Allowlist (`servicio_teleconsulta_caso`) | Códigos de triage permitidos cuando política = `algunas` |
| `TeleconsultaElegibilidadService` | Compila triage + servicio → `teleconsulta_ofrecible`, `tipo_atencion` forzado o sugerido |
| Draft hydrator (`scheduling.reserva_triage`) | Escribe flags en el draft del asistente tras cada paso |
| Agenda PES (`acepta_consultas_online`) | El profesional indica si atiende teleconsulta en su agenda |
| Listado profesionales | Con `tipo_atencion=teleconsulta` solo PES con agenda que acepta online |

## Reglas clínicas (triage)

1. **Halt / banda A** → no teleconsulta; no se completa reserva (urgencia).
2. **Banda B** → presencial preferido (no se ofrece elección remota).
3. Nodos con `teleconsulta_elegibilidad` explícita prevalecen sobre la raíz genérica.
4. Raíz `seguimiento_cronico` → sugerido; `estudio_pedido` → **excluido** (estudios y prácticas son presenciales en el centro).
5. Raíz `tramite_admin` → permitido (si el servicio lo admite).

## Reglas de servicio

- **Hub paciente (`medicina_clinica`):** autogestión directa; teleconsulta según política del servicio y triage.
- **Especialistas:** no autogestión; teleconsulta solo con derivación del clínico (`derivacion_especialista`).
- **`ninguna`** (default tras migración): presencial en reserva hub; se salta modalidad remota.
- **`todas` / `algunas`:** ver allowlist `servicio_teleconsulta_caso` para hub; especialistas con derivación requieren política que permita remoto.

Configuración operativa: asistente **Política de teleconsulta por servicio** o columna `servicios.teleconsulta_politica` + allowlist.

## Flujo del asistente (`atencion.necesito-atencion`)

Orden: triage → **servicio** → **modalidad** (condicional) → centro → profesional → día → horario.

Tras elegir servicio: si hay videollamada ofrecible, sigue el paso de modalidad; si no, se fija presencial y salta a centro.

Al persistir un turno de videollamada, el dominio comprueba que la agenda vigente del profesional acepte consultas online.

## Personal de salud

La agenda del día muestra **Presencial** / **Teleconsulta** en cada turno.

Insight en listado del día, consulta por mensaje y política por servicio: [atencion-remota-async.md](./atencion-remota-async.md).

Ver también: [triage-reserva-turno.md](./triage-reserva-turno.md), [turnos.md](./turnos.md), [medicina-clinica-hub-reserva.md](./medicina-clinica-hub-reserva.md).

QA integrado: [../qa/escenarios/ambulatorio/teleconsulta.md](../qa/escenarios/ambulatorio/teleconsulta.md).
