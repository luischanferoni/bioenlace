# Fase 04 — Creación de la cuenta y cierre del alta

## Objetivo

Que la computadora, al ver el vínculo `verified`, cree o reutilice la cuenta con el centro elegido, registre la solicitud de incorporación y muestre el mismo estado informativo que la app.

## Tareas

- [ ] `actionCompletar()` (JSON, sesión de la computadora): exige estado `verified` y `id_efector` válido; llama a `CreateIdentityAccount` y, si corresponde, `StaffAffiliationRequestService::record`, en ese orden y dentro del mismo flujo que `LicenciaController::actionCrearCuenta`.
- [ ] Reutilizar `SignupAbuseGuard` en `completar` para no abrir un camino de alta sin límite.
- [ ] Persistir `id_persona` e `id_user` en el vínculo (`account_created`) y devolver al navegador sólo `es_nueva_persona`, `es_nuevo_usuario` y el estado de la solicitud; nunca identificadores internos ni el `verification_id`.
- [ ] Mapear errores con el mismo criterio que el alta existente: 400 por datos inválidos, 422 por verificación no aprobada, 429 por abuso, 500 genérico con log.
- [ ] Reintento idempotente: repetir `completar` sobre un vínculo ya `account_created` devuelve el mismo resultado sin volver a crear Persona, User ni solicitud.
- [ ] Pantalla de resultado en la computadora: cuenta creada con solicitud enviada al centro (sin asignación todavía), cuenta ya existente con solicitud registrada, y error con reintento.
- [ ] `vinculo-cancelar` deja el vínculo cerrado y la pantalla vuelve al inicio del alta.
- [ ] Verificar que no se crea sesión web ni cookie de autenticación en todo el recorrido.

## Criterio de salida

Un alta nueva deja una Persona, un User con perfil Paciente y una solicitud de incorporación, sin sesión web; un alta con identidad existente reutiliza Persona y User y no duplica la solicitud; repetir el último paso no crea duplicados; cancelar y expirar no crean nada.
