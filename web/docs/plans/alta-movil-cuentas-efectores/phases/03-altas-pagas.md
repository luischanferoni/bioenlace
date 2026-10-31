# Fase 03 — Consultorio y clínica/centro con checkout

## Objetivo

Crear consultorio o clínica desde la app con confirmación informada e idempotente del pago.

## Checklist

- [x] Definir proveedor de pagos: MercadoPago con estructura básica de integración.
- [x] Crear servicio `MercadoPagoCheckoutService` con métodos createPreference, getPaymentStatus, processWebhook.
- [x] Crear controlador `MercadoPagoWebhookController` para recibir notificaciones de pago.
- [x] Configurar parámetros `mercadoPago` en `common/config/params.php` (sandboxMode, accessToken, etc.).
- [x] Documentar estructura base: código con bloques de integración real comentados, modo sandbox por defecto.
- [ ] Consultorio: KYC + REFEPS aprobado + datos del efector + plan + términos antes del checkout.
- [ ] Clínica/centro: KYC + datos del efector + plan + términos; sin requisito REFEPS para AdminEfector.
- [ ] Mostrar importe, moneda, impuestos, recurrencia, renovación, cancelación y soporte antes de pagar.
- [ ] Completar integración real con MercadoPago: checkout alojado/tokenizado, sin guardar PAN/CVV.
- [ ] Persistir estado del alta y cada intento de pago; idempotencia, reconciliación y webhooks firmados.
- [ ] No activar la licencia ni permisos de pago antes de la confirmación del proveedor.
- [ ] Reanudar errores/rechazos desde el registro persistido sin duplicar efector, usuario o cobro.
- [ ] Crear AdminEfector y rol clínico por separado; consultorio propio requiere ambos dominios.

## Criterio de salida

Pruebas de aprobación, rechazo, timeout, webhook repetido y reintento prueban ausencia de duplicados y de acceso prematuro.

El endpoint legado de alta institucional con pago simulado fue retirado (`410 Gone`). No se crea una licencia hasta integrar un proveedor real y confirmar el pago.
