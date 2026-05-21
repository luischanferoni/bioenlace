# Reprogramación (UI separada)

## Objetivo

Permitir al paciente elegir otro slot y reprogramar un turno pendiente, respetando grilla y política de autogestión.

## Actores

- Paciente (web `turnos/reprogramar` o API).
- Sistema (validación `TurnoReservaSlotService`, política moderada → 409).

## Anclas

| Paso | Ruta |
|------|------|
| Slots alternativos | `GET /api/v1/turnos/{id}/slots-alternativos` |
| Reprogramar | `POST /api/v1/turnos/{id}/reprogramar` |
| Intent asistente | `turnos.modificar-como-paciente-flow` |

## Web

Ruta: `turnos/reprogramar` — lista turnos pendientes futuros del paciente en sesión y documenta endpoints de API para slots y reprogramación.

## API

1. `GET api/v1/turnos/{id}/slots-alternativos` — query `limit`, `max_dias`, `mismo_profesional` (1|0). Permiso `/api/turnos/slots-alternativos-como-paciente`.
2. `POST api/v1/turnos/{id}/reprogramar` — body: `fecha`, `hora`, **`id_profesional_efector_servicio`** (obligatorio), o `slot_id` (`pes:…|fecha|hora|intervalo`).

La reprogramación valida grilla y solapamiento (`TurnoReservaSlotService`) y persiste `hora_fin`, `intervalo_minutos_reserva`, `id_agenda_version` cuando aplica. Ver [agenda-intervalo-y-reservas.md](./agenda-intervalo-y-reservas.md).

Si la política de autogestión es **moderada**, respuesta **409** `REPROGRAM_POLICY_MODERADA`.
