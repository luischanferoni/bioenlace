# Reprogramación (UI separada)

## Web

Ruta: `turnos/reprogramar` — lista turnos pendientes futuros del paciente en sesión y documenta endpoints de API para slots y reprogramación.

## API

1. `GET api/v1/turnos/{id}/slots-alternativos` — query `limit`, `max_dias`, `mismo_profesional` (1|0). Permiso `/api/turnos/slots-alternativos-como-paciente`.
2. `POST api/v1/turnos/{id}/reprogramar` — body: `fecha`, `hora`, `id_rrhh_servicio_asignado` (opcional si no cambia).

Si la política de autogestión es **moderada**, respuesta **409** `REPROGRAM_POLICY_MODERADA`.
