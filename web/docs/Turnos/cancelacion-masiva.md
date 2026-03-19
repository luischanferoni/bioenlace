# Cancelación masiva por día

## API

`POST api/v1/turnos/bulk-cancel-dia` — requiere rol **AdminEfector**. Body JSON:

- `fecha` (Y-m-d), obligatorio
- `id_rr_hh` opcional (solo turnos de ese profesional)

## Web

`POST turnos/bulk-cancel-dia` (mismo cuerpo, usuario sesión con AdminEfector).

## Reglas

`efector_turnos_config.cancelacion_masiva` debe estar habilitado.

Marca turnos `CANCELADO` con motivo médico, soft delete, cancela notificaciones pendientes y envía push al paciente.
