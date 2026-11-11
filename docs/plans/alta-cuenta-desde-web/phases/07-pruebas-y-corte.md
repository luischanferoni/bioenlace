# Fase 07 — Pruebas, QA y corte

## Objetivo

Cerrar el plan con evidencia de que los recorridos funcionan en dispositivo real, que el vínculo no se puede abusar, que el alta paga avisa y se puede retomar, y que la documentación estable quedó actualizada.

## Tareas

- [ ] Suite completa del vínculo y del alta: `cd web/common && ../vendor/bin/codecept run unit organization` y la suite unit completa.
- [ ] Pruebas de integración del controlador del alta con sesión simulada: iniciar, reclamar, autorizar, completar y cancelar; y binding de sesión incorrecto.
- [ ] QA manual con dos dispositivos reales, checklist por recorrido:
  - [ ] Cuenta nueva: elegir centro → QR → código → Didit aprobada → cuenta creada con solicitud.
  - [ ] Identidad existente: mismo recorrido, resultado "cuenta ya existente" y sin duplicados en `personas` ni `user`.
  - [ ] Código incorrecto cinco veces: el vínculo pasa a `failed` y no permite verificar.
  - [ ] Token abierto dos veces (dos teléfonos): el segundo recibe "vínculo ya utilizado".
  - [ ] Abandono: cerrar la pestaña de la computadora a mitad del recorrido no crea cuenta y el vínculo expira.
  - [ ] Didit cancelada o rechazada: mensaje claro y reintento sin rehacer el vínculo.
  - [ ] Reintento tras error de red en `completar`: no duplica Persona, User ni solicitud.
  - [ ] Recarga de la computadora durante el vínculo: el estado se recupera o se reinicia sin datos inconsistentes.
  - [ ] Alta paga: cerrar la pestaña en el paso 2 y volver más tarde recupera el borrador y el paso exactos.
  - [ ] Alta paga sin e-mail confirmado: el checkout queda bloqueado y el mensaje explica cómo confirmarlo.
  - [ ] Pago confirmado en el ambiente de prueba: llega un único aviso al e-mail confirmado y el estado del alta lo refleja.
  - [ ] Ingreso con app instalada: el QR abre la app (Universal/App Link), la huella desbloquea y la web inicia sesión sin costo de Didit.
  - [ ] Ingreso sin app: el QR abre el navegador del teléfono, se verifica identidad, se transcribe el código y la web inicia sesión.
  - [ ] Sesión revocada: el desbloqueo biométrico del teléfono ya no aprueba accesos.
  - [ ] Ningún dato del alta queda en `localStorage`, `sessionStorage` ni cookies del navegador.
- [ ] Revisar que ningún endpoint acepte `verification_id` del cliente y que ninguna respuesta JSON exponga el `verification_id`, el token ni identificadores internos de la cuenta.
- [ ] Revisar los límites por IP con un cliente automatizado y confirmar que devuelven 429.
- [ ] Al cerrar: volcar a documentación estable
  - `docs/producto/alta-cuenta-licencia.md`: canales de alta (app y web con teléfono), estado de la cuenta creada, borrador y aviso de pago.
  - `docs/qa/` (carpeta de staff): pasos de prueba de los recorridos nuevos.
  - `docs/decisions/`: acceso web del titular que no usa la app, según la decisión de la fase 06.
- [ ] Borrar `docs/plans/alta-cuenta-desde-web/` y quitar su fila de `docs/plans/README.md`.

## Criterio de salida

Los recorridos pasan en dispositivo real; las pruebas unitarias y de integración están en verde; el QA negativo (código agotado, token reutilizado, abandono, rechazo, checkout sin e-mail) se comporta como se describe; el aviso de pago llega una sola vez; la documentación estable refleja los canales nuevos y el plan queda cerrado.
