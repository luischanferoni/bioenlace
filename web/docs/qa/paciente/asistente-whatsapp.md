# Smoke — asistente WhatsApp (paciente)

Checklist manual del MVP (Meta Cloud API). Requiere `whatsapp.*` en params-local, migración aplicada y webhook HTTPS apuntando a `GET|POST /api/v1/whatsapp/webhook`.

## Setup Meta

0. Smoke de deploy (sin token): `GET /api/v1/whatsapp/ping` → JSON `{"ok":true,"service":"whatsapp-webhook"}`.
1. Verify challenge: Meta recibe 200 y el `hub.challenge` cuando el verify token coincide.
2. Un POST con firma `X-Hub-Signature-256` inválida responde 401.
3. Un POST firmado correctamente responde 200 aunque el cuerpo esté vacío.

## Vinculación

1. Persona paciente activa con teléfono en `persona_telefono` que matchee el número WA (últimos dígitos).
2. Primer mensaje desde ese WA → pregunta «¿Sos {nombre}? Respondé SI…».
3. Responder `SI` → mensaje de vínculo OK + menú de atajos MVP.
4. Responder `NO` (en otro número de prueba) → no vincula; mensaje de rechazo.
5. Número sin cuenta → mensaje pidiendo registrarse en la app y cargar el teléfono.

## Menú y NL

1. Escribir `menú` / `hola` con vínculo activo → lista o botones de atajos (turnos, lab, recetas, queja).
2. Elegir un atajo → respuesta del motor (texto/botones) o deep link si el paso pide UI rica.
3. Escribir `mis turnos` → resumen de turnos pendientes (YAML `turnos.ver-mis-turnos-como-paciente`; el paciente ya autorizado a `/api/turnos/listar-como-paciente` alcanza el intent vía `rbac_route`. Opcional: migración `m260711_180000_…` o `php yii catalog-permission/sync`).
4. Escribir en castellano p. ej. «quiero cancelar un turno» → respuesta útil o degradación a app.
5. Reenviar el mismo `wamid` (o duplicar el webhook) → no duplica efecto (idempotencia).

## Fuera de alcance (no fallar el smoke)

Media/audio entrante, templates proactivos, paridad de todos los atajos de la app móvil.
