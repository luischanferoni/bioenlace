# Configuración de mockups — SISA y MercadoPago

## SISA (REFEPS)

### Archivo: `common/components/Domain/Person/Identity/Infrastructure/External/Sisa/SisaAdapter.php`

Clase adaptadora mockup para verificación REFEPS en desarrollo, siguiendo estructura DDD (Infrastructure/External).

### Estado y límites

El mock se mantiene por ahora para desarrollo y pruebas. No es una integración real ni debe presentarse como una consulta efectiva al padrón. La integración HTTP de producción y la semántica nacional del endpoint provincial deben verificarse antes de sustituirlo.

- **Modo mockup (`sisa.mockMode = true`):**
  - Simula respuesta exitosa de REFEPS para cualquier profesional
  - Devuelve un profesional con el documento solicitado
  - No hace llamadas HTTP reales

### Configuración en `common/config/params.php`

```php
'sisa' => [
    'mockMode' => true,  // true = mockup, false = integración real
    'appId' => null,     // Configurar en params-local.php para producción
    'appKey' => null,    // Configurar en params-local.php para producción
    'baseUrl' => null,   // Configurar en params-local.php para producción
],
```

No cambiar `mockMode` a `false` como si la integración real ya estuviera disponible. Antes de hacerlo, implementar y probar el conector real, validar el alcance nacional informado y asegurar que la aplicación no use datos simulados en producción.

El alta de clínicas públicas no usa este mock REFEPS. Busca el código SISA exacto en el catálogo local `efectores`, alimentado por el archivo oficial de establecimientos. Los códigos no encontrados o marcados `INACTIVO`, las afiliaciones ministeriales y las cuentas POOL existentes se rechazan. Mantener el catálogo actualizado mediante el proceso administrativo de importación/desactivación SISA.

3. Verificar formato de respuesta según documentación de SISA

4. Implementar lógica de parsing de respuesta real (si difiere del mockup)

---

## Mercado Pago (suscripciones)

### Archivos

- `common/components/Domain/Organization/Billing/Application/Service/MercadoPagoCheckoutService.php`
- `frontend/modules/api/v1/controllers/organization/MercadoPagoWebhookController.php` (API v1, no web MVC)

### Comportamiento implementado

- `POST /api/v1/licencia/registrar-efector` requiere una sesión de la cuenta móvil.
- Se crea una suscripción mensual en `/preapproval` y se devuelve `init_point` para el checkout hospedado.
- Los intentos guardan el snapshot del plan, MEP vendedor, IVA y total ARS; la clave que envía la app se reutiliza como clave idempotente.
- El efector, la cuenta comercial, los entitlements y el rol AdminEfector se crean solo después de obtener el primer pago aprobado desde `/authorized_payments/{id}`.
- El webhook acepta los tópicos `subscription_preapproval` y `subscription_authorized_payment`, valida `X-Signature`, consulta el recurso en la API y deduplica eventos.
- Cancelar desde la app detiene la renovación pero conserva la licencia hasta `paid_through_at`; un comando diario suspende cuentas vencidas. Cada pago aprobado extiende el período un mes.
- Las suscripciones usan credenciales de prueba o producción según el `accessToken`. No hay un modo local que simule pagos aprobados.

### Configuración en `common/config/params.php`

```php
'mercadoPago' => [
    'accessToken' => null,       // Token de prueba o producción; mantener fuera del repositorio
    'webhookSecret' => null,     // Clave de firma configurada para la URL del webhook
    'notificationUrl' => null,   // URL pública HTTPS del endpoint
    'backUrl' => null,           // URL HTTPS a la que vuelve el usuario
    'currency' => 'ARS',
    'usdArsRate' => null,        // Cotización MEP vendedora revisada trimestralmente
    'usdArsRateEffectiveDate' => null,
    'vatRate' => 0.21,
],
```

### Para habilitar integración real

1. Configurar en `common/config/params-local.php`:
   ```php
   'mercadoPago' => [
       'accessToken' => getenv('MERCADOPAGO_ACCESS_TOKEN'),
       'webhookSecret' => getenv('MERCADOPAGO_WEBHOOK_SECRET'),
       'notificationUrl' => 'https://<host>/api/v1/mercado-pago-webhook',
       'backUrl' => 'https://<host>/<checkout-return-route>',
       'currency' => 'ARS',
       'usdArsRate' => (float) getenv('MERCADOPAGO_USD_ARS_RATE'),
       'usdArsRateEffectiveDate' => getenv('MERCADOPAGO_USD_ARS_RATE_DATE'),
       'vatRate' => 0.21,
   ],
   ```
   No guardar secretos, cotizaciones reales ni credenciales de producción en Git. La cotización debe tener una fecha vigente (máximo 100 días).

2. Configurar webhook URL en la consola de MercadoPago:
   - URL: el valor de `notificationUrl`, por HTTPS.
   - Eventos: `subscription_preapproval` y `subscription_authorized_payment`.
   - Copiar la clave de firma que Mercado Pago genera al configurar las notificaciones a `webhookSecret`.

3. Ejecutar pruebas de sandbox de alta, aprobación, rechazo, reintento y webhook repetido antes de habilitar tráfico real.

4. Antes de producción: verificar vigencia del catálogo SISA local, ejecutar el cron `php yii billing-subscription/expire-paid-through`, probar cancelación/renovación en sandbox y definir reembolsos/contracargos.

---

## Notas generales

- REFEPS profesional continúa en modo mock; el catálogo local de efectores SISA es distinto y se consulta por código oficial. Mercado Pago usa la API real cuando se configura el token correspondiente.
- Sin token, secreto de webhook, URLs HTTPS y cotización MEP vigente el checkout queda cerrado.
- Los parámetros sensibles (tokens, secretos) deben configurarse en `params-local.php`
- Los archivos `params-local.php` no deben incluirse en git (ya están en .gitignore)
