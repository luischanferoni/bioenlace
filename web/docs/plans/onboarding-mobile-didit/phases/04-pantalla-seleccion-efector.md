# Fase 4 — Pantalla de selección de efector (estado vacío)

## Objetivo

Dar salida al profesional sin efector en la pantalla post-login "seleccionar efector".

## Entregables

- Web ([indexuserefector.php] + [sesion-operativa-postlogin-wizard.js]) y app ([config_wizard_screen.dart]) muestran, cuando no hay efectores:
  - mensaje: "Si trabajás en un efector, pedile al administrador que te agregue";
  - leyenda + botón "Crear mi consultorio" que lleva al formulario de pago.
- `SesionOperativaController` devuelve flags para renderizar las acciones (`puede_crear_efector`, `invitacion_pendiente`, `cuenta_pendiente_pago`).

## Decisiones a cerrar

- Si el botón del estado vacío es solo consultorio o también clínica/centro.
- Texto final de los mensajes.

## Criterios de aceptación

- Un usuario sin efector ve el mensaje y el botón; el botón lleva al alta de efector propio.
- Un usuario con efector no ve el estado vacío (sin regresión).
