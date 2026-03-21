# Cancelación masiva por día

## API

`POST api/v1/turnos/cancelar-dia-efector` — requiere rol **AdminEfector** (permiso `/api/turnos/cancelar-dia-efector`). Body JSON:

- `fecha` (Y-m-d), obligatorio
- `id_rr_hh` opcional (solo turnos de ese profesional)

## Web

`POST turnos/cancelar-dia-efector` (mismo cuerpo, usuario sesión con AdminEfector).

## Reglas

`efector_turnos_config.cancelacion_masiva` debe estar habilitado.

Marca turnos `CANCELADO` con motivo médico, soft delete, cancela notificaciones pendientes y envía push al paciente.
