# Política de cancelaciones y autogestión

## Niveles

1. **OK** — por debajo del umbral suave: sin mensaje extra.
2. **SUAVE** — entre umbral suave y moderado: se permite autogestión; se muestra mensaje orientativo.
3. **MODERADA** — por encima del umbral moderado: **no** se permite cancelar/reprogramar/reservar vía app hasta liberación; sí presencial o llamada verificada en el efector.

Parámetros en `efector_turnos_config` (backend: Efector → Config. turnos).

## Liberación

Registro en `persona_efector_autogestion_liberacion` (formulario en la misma pantalla de config). Válido según `autogestion_liberacion_vigencia_dias`.

## Conteo

Solo turnos **cancelados por paciente** (`CANCELADO_X_PACIENTE`) con soft delete en la ventana `cancel_ventana_dias` (`Turno::findInactive()` + `deleted_at`).

## API

- `GET api/v1/turnos/politica-autogestion` — estado para el paciente autenticado.
- Cancelación app: `POST api/v1/turnos/{id}/cancelar` con `canal=app` puede devolver **409** `CANCEL_POLICY_MODERADA`.
