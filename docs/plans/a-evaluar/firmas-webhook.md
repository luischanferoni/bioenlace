# A evaluar — verificación de firmas de webhooks

**Estado:** sin planificar. Falta evidencia para decidir si justifica un plan.

## Qué se midió

`hash_equals(` aparece en **5 archivos / 9 usos**:

| Archivo | Qué verifica |
|---------|--------------|
| `Organization/Billing/Application/Service/MercadoPagoCheckoutService` | HMAC-SHA256 de un manifest `id:…;request-id:…;ts:…;` contra header `ts=…,v1=…` |
| `api/v1/controllers/integrations/WhatsAppWebhookController` | Firma del webhook de Meta (2 usos) |
| `Platform/Core/Auth/PasskeyAuthenticationService` | WebAuthn (4 usos) |
| `Platform/Core/Auth/DemoSandboxCaptchaService` | Captcha de sandbox |
| `api/v1/controllers/scheduling/TurnosController` | Comparación puntual |

## Lo que todavía no se sabe

1. **¿MP y WhatsApp comparten formato de verdad?** Ambos parsean headers `clave=valor` y usan
   HMAC, pero cambian el manifest (qué se firma y en qué orden) y el nombre del header.
   Compartir sólo el *parseo* puede no justificar una abstracción.
2. **¿Passkey y captcha son el mismo problema?** Passkey es WebAuthn (desafío + clave pública),
   no HMAC. Probablemente sean casos distintos y no deban compartir nada.
3. **¿`TurnosController` es una firma o una comparación de tokens?** Hay que leerlo antes de
   contarlo.
4. **¿Hay firmas que hoy no se verifican?** Vale revisar otros webhooks entrantes
   (por ejemplo el de agendamiento FHIR) donde falte validación — más urgente que deduplicar.

## Qué habría que averiguar antes de planificar

- Leer los 5 usos y clasificarlos en: HMAC de header, WebAuthn, comparación de secreto.
- Si sólo 2 quedan en la misma clase, el plan no se justifica: se resuelve como parte de la
  fase 02 (MP) y de un ticket chico (WhatsApp).
- Si aparece un webhook **sin** verificación, ese es el problema real y va primero.

## Recomendación preliminar

No planificar todavía. Prioridad sugerida:

1. Auditar qué webhooks entrantes no verifican firma (riesgo de seguridad, no de DRY).
2. Si quedan 3+ verificadores HMAC con el mismo formato, abrir plan con un
   `Shared/Infrastructure/Http/WebhookSignature` (parseo de header + HMAC timing-safe).

Mientras tanto, la verificación de Mercado Pago **no se toca**: tiene test y está en producción.
