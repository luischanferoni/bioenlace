# Configuración de mockups — SISA y MercadoPago

## SISA (REFEPS)

### Archivo: `common/components/Domain/Person/Identity/Infrastructure/External/Sisa/SisaAdapter.php`

Clase adaptadora mockup para verificación REFEPS en desarrollo, siguiendo estructura DDD (Infrastructure/External).

### Comportamiento

- **Modo mockup (`sisa.mockMode = true`):**
  - Simula respuesta exitosa de REFEPS para cualquier profesional
  - Devuelve un profesional con el documento solicitado
  - No hace llamadas HTTP reales

- **Modo producción (`sisa.mockMode = false`):**
  - Requiere configuración completa de credenciales
  - Realiza llamadas HTTP a la API de SISA
  - Valida respuesta real del registro de Santiago

### Configuración en `common/config/params.php`

```php
'sisa' => [
    'mockMode' => true,  // true = mockup, false = integración real
    'appId' => null,     // Configurar en params-local.php para producción
    'appKey' => null,    // Configurar en params-local.php para producción
    'baseUrl' => null,   // Configurar en params-local.php para producción
],
```

### Para habilitar integración real

1. Configurar en `common/config/params-local.php`:
   ```php
   'sisa' => [
       'mockMode' => false,
       'appId' => 'your-app-id',
       'appKey' => 'your-app-key',
       'baseUrl' => 'https://sisa.msal.gob.ar',
   ],
   ```

2. Descomentar el bloque de conexión HTTP en `Sisa::getProfesionalesDeSantiago()`

3. Verificar formato de respuesta según documentación de SISA

4. Implementar lógica de parsing de respuesta real (si difiere del mockup)

---

## MercadoPago (Pagos)

### Archivos

- `common/components/Domain/Organization/Billing/Application/Service/MercadoPagoCheckoutService.php`
- `frontend/modules/api/v1/controllers/organization/MercadoPagoWebhookController.php` (API v1, no web MVC)

### Comportamiento

- **Modo sandbox (`mercadoPago.sandboxMode = true`):**
  - Simula creación de preferencias de pago
  - Devuelve IDs mockup de preferencia
  - No hace llamadas HTTP reales
  - Webhooks son procesados pero no validados

- **Modo producción (`mercadoPago.sandboxMode = false`):**
  - Requiere access token de MercadoPago
  - Realiza llamadas HTTP a la API de MercadoPago
  - Crea preferencias reales con precios del catálogo
  - Procesa webhooks firmados para confirmación de pago

### Configuración en `common/config/params.php`

```php
'mercadoPago' => [
    'sandboxMode' => true,       // true = sandbox, false = producción
    'accessToken' => null,       // Configurar en params-local.php
    'currency' => 'ARS',
    'successUrl' => null,        // Configurar en params-local.php
    'failureUrl' => null,        // Configurar en params-local.php
    'pendingUrl' => null,        // Configurar en params-local.php
    'webhookSecret' => null,     // Configurar en params-local.php para validar firmas
],
```

### Para habilitar integración real

1. Configurar en `common/config/params-local.php`:
   ```php
   'mercadoPago' => [
       'sandboxMode' => false,
       'accessToken' => 'your-access-token',
       'currency' => 'ARS',
       'successUrl' => 'https://app.bioenlace.io/checkout/success',
       'failureUrl' => 'https://app.bioenlace.io/checkout/failure',
       'pendingUrl' => 'https://app.bioenlace.io/checkout/pending',
       'webhookSecret' => 'your-webhook-secret',
   ],
   ```

2. Configurar webhook URL en la consola de MercadoPago:
   - URL: `https://app.bioenlace.io/mercadopago-webhook`
   - Eventos: `payment.created`, `payment.updated`

3. Descomentar bloques de integración real en:
   - `MercadoPagoCheckoutService::createPreference()`
   - `MercadoPagoCheckoutService::getPaymentStatus()`
   - `MercadoPagoCheckoutService::processWebhook()`
   - `MercadoPagoWebhookController::validateWebhookSignature()`

4. Implementar catálogo de planes (precios, recurrencia, impuestos)

5. Implementar validación de firmas de webhook (HMAC-SHA256)

6. Implementar conciliación de intentos de pago (idempotencia)

7. Implementar activación de licencias/entitlements tras confirmación

---

## Notas generales

- Ambos mockups están diseñados para permitir desarrollo sin dependencias externas
- La integración real se activa cambiando un parámetro de configuración
- Todo el código de integración real está comentado en los archivos correspondientes
- Los parámetros sensibles (tokens, secretos) deben configurarse en `params-local.php`
- Los archivos `params-local.php` no deben incluirse en git (ya están en .gitignore)
