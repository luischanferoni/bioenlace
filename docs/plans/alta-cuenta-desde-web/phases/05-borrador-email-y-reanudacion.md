# Fase 05 — Borrador, e-mail y reanudación desde la web

## Objetivo

Que un alta empezada en la web se pueda abandonar y retomar sin perder lo cargado, y que el alta paga exija un e-mail válido y confirmado antes de ir al checkout.

## Punto de partida (ya existe)

- El borrador comercial vive en el servidor: `commercial_signup_draft`, único por `id_persona`, con `status` (`DRAFT`, `CHECKOUT_PENDING`, `ACTIVE`, `EXPIRED`), `current_step`, `efector_json`, `plan_json`, `payer_email`, `last_activity_at` y `expires_at`.
- `POST /api/v1/licencia/guardar-borrador` guarda por persona revalidando la prueba Didit; `POST /licencia/crear-cuenta` devuelve el borrador recuperado.
- La app móvil no guarda el borrador localmente: guarda el `verification_id` para no repetir Didit tras un fallo de red.
- El e-mail tiene envío y consulta de estado (`enviar-verificacion-email`, `estado-email`) con cooldown, y hoy no bloquea el checkout.

## Tareas

- [ ] Dejar explícito en el diseño y en el código que **la web no guarda estado en el navegador**: lo transitorio vive en el vínculo y lo persistente en `commercial_signup_draft`.
- [ ] Autoguardado en cada cambio de paso del alta web (mismo criterio que la app): guardar borrador desde el servidor con el `verification_id` del vínculo, sin exponerlo al navegador.
- [ ] Reanudación: al reabrir `/alta`, el titular elige centro (o "continuar mi alta"), completa el vínculo y el servidor devuelve el borrador de `findForPersona` con `current_step`, para retomar en el paso donde quedó.
- [ ] Si el vínculo expiró pero el borrador sigue vigente, se repite la verificación y el borrador se recupera igual: la cuenta y el borrador no dependen del vínculo.
- [ ] Alta paga: exigir e-mail válido **y confirmado** antes de iniciar el checkout, con los endpoints existentes; mostrar estado, reenvío y cooldown. El alta de cuenta profesional gratuita sigue sin requerir e-mail.
- [ ] Validar el formato en cliente y servidor (normalización a minúsculas, `FILTER_VALIDATE_EMAIL`); la prueba real de titularidad es la confirmación por token, no el formato.
- [ ] Rechazar el avance del checkout si el e-mail no está confirmado, con un mensaje que explique cómo confirmarlo y cómo cambiarlo.
- [ ] Persistir el e-mail en el borrador (`payer_email`) y en la cuenta con el mismo criterio que ya usa `BillingCheckoutService`.
- [ ] Reverificar el comportamiento de `expires_at` del borrador en la web: limpiar borradores vencidos al recuperar y avisar antes de perder uno a punto de vencer.
- [ ] Tests: autoguardado por paso, reanudación por persona, checkout bloqueado sin e-mail confirmado, checkout habilitado con e-mail confirmado, borrador vencido no se reutiliza.

## Criterio de salida

Cerrar la pestaña en el paso 2 del alta paga y volver más tarde recupera el borrador exacto; el checkout no se puede iniciar sin e-mail confirmado; la cuenta profesional gratuita se sigue creando sin e-mail; ningún dato del alta queda almacenado en `localStorage`, `sessionStorage` ni cookies del navegador.
