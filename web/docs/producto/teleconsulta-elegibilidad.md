# Elegibilidad de teleconsulta al reservar turno

## Objetivo

Decidir de forma **simple y honesta** si el paciente puede elegir **modalidad remota** (teleconsulta) o si el flujo fija **presencial** y omite el paso de elección.

No es diagnóstico: combina triage de reserva, política del servicio y configuración de agenda del profesional.

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
4. Raíz `control_cronico` → sugerido; `tramite_admin` → permitido (si el servicio lo admite).

## Reglas de servicio

- **`ninguna`** (default tras migración): siempre presencial en reserva; se salta modalidad.
- **`todas`**: cualquier caso elegible clínicamente puede ofrecer teleconsulta.
- **`algunas`**: solo si algún código del recorrido de triage está en `servicio_teleconsulta_caso`.

Configuración operativa: actualizar `servicios.teleconsulta_politica` y filas en allowlist (sin UI admin dedicada en fase 1).

## Flujo del asistente (`atencion.necesito-atencion`)

Orden: triage → **servicio** → **modalidad** (condicional) → centro → profesional → día → horario.

Tras elegir servicio, el hydrator fija:

- `teleconsulta_ofrecible = 1` → siguiente paso `select_tipo_atencion`.
- `teleconsulta_ofrecible = 0` → `tipo_atencion = presencial` y salto directo a centro.

API modalidad: `GET /api/v1/turnos/reserva-triage-paso?step=modalidad&id_servicio_asignado=…` filtra opciones vía `TeleconsultaElegibilidadService::opcionesModalidadParaDraft()`.

## Agenda del profesional

En **Configurar agenda** (`/api/v1/profesional-agenda/configurar-agenda`), campo `acepta_consultas_online`.

Al persistir turno teleconsulta, `TurnoPersistService::assertAgendaAceptaTeleconsultaPorPes` valida que la agenda vigente del PES lo permita.

## App médico

La agenda del día expone `tipo_atencion` en cada turno; la lista muestra badge **Presencial** / **Teleconsulta**.

## Migración

`m260607_100000_servicios_teleconsulta_politica`: columna `teleconsulta_politica` + tabla `servicio_teleconsulta_caso`.

## Evolución prevista

- UI staff para editar política y allowlist por servicio.
- Códigos SNOMED / terminología clínica en lugar de solo códigos internos de triage.
- Slots diferenciados presencial/remoto (hoy comparten grilla).

Ver también: [triage-reserva-turno.md](./triage-reserva-turno.md), [turnos.md](./turnos.md).
