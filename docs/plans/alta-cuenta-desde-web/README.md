# Plan — Alta de cuenta desde la web con el teléfono

| Campo | Valor |
|-------|--------|
| Slug | `alta-cuenta-desde-web` |
| Estado | Abierto — diseño acordado, fases 00–07 pendientes |
| Dueño | Producto / Identidad / Organización / Web |
| Corte de compatibilidad | Ninguno: el alta móvil y el login web por aprobación siguen igual |

## Índice

- [overview.md](overview.md) — recorridos, alcance y límites
- [design.md](design.md) — decisiones cerradas, estados del vínculo y seguridad
- [phases/00-decisiones-y-modelo.md](phases/00-decisiones-y-modelo.md) — cierre de decisiones y tabla `signup_pairing`
- [phases/01-vinculo-telefono.md](phases/01-vinculo-telefono.md) — servicio del vínculo navegador ↔ teléfono
- [phases/02-pagina-web-y-qr.md](phases/02-pagina-web-y-qr.md) — página `/alta`, QR y código
- [phases/03-pagina-telefono-y-didit.md](phases/03-pagina-telefono-y-didit.md) — página del teléfono y verificación Didit
- [phases/04-alta-cuenta-y-cierre.md](phases/04-alta-cuenta-y-cierre.md) — creación de cuenta y solicitud de incorporación
- [phases/05-borrador-email-y-reanudacion.md](phases/05-borrador-email-y-reanudacion.md) — borrador del servidor, e-mail confirmado y reanudación
- [phases/06-ingreso-web-y-aviso-de-pago.md](phases/06-ingreso-web-y-aviso-de-pago.md) — ingreso web sin costo (app, passkey del teléfono, recuperación asistida) y aviso de pago
- [phases/07-pruebas-y-corte.md](phases/07-pruebas-y-corte.md) — pruebas, QA y cierre del plan

## Reglas de ejecución

- La verificación de identidad se ejecuta **siempre** en el teléfono; la computadora no pide cámara, documento ni selfie.
- El servidor es la única autoridad: el `verification_id` que devuelve el navegador nunca se acepta como prueba; se consulta Didit desde el backend.
- El QR contiene **solo** una URL temporal con token opaco. No lleva datos personales, ni efector, ni código de emparejamiento.
- El código de emparejamiento se muestra únicamente en el navegador que inició el vínculo y recién cuando el teléfono abrió la URL. Es la prueba de que quien opera el teléfono ve la pantalla de la computadora.
- El alta reutiliza los casos de uso existentes (`CreateIdentityAccount`, `StaffAffiliationRequestService`); no se duplican reglas de cuenta, identidad ni afiliación.
- Al terminar **no** se crea sesión web: la cuenta queda con perfil Paciente y la solicitud de incorporación registrada. El acceso web sigue siendo por aprobación desde la app.
- La compra de espacio de atención desde la web entra en las fases 05–06: el borrador vive en el servidor (nunca en el navegador), el checkout exige e-mail confirmado y el pago confirmado se avisa por e-mail.
- Todo dato nuevo de esquema entra por migración idempotente en `web/common/migrations/`.

## Criterio de cierre

Los tres recorridos de [overview.md](overview.md) funcionan de punta a punta en un dispositivo real; ningún endpoint acepta una verificación sin consultarla contra Didit; el vínculo es de un solo uso, expira y limita intentos de código; una identidad existente reutiliza su cuenta sin duplicar Persona ni User; una alta paga abandonada se retoma desde el borrador del servidor; el checkout no avanza sin e-mail confirmado; el pago confirmado genera un único aviso al titular; y las pruebas unitarias del vínculo cubren expiración, intentos agotados, token reutilizado y binding de sesión.
