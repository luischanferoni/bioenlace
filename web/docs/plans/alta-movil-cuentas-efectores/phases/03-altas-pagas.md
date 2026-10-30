# Fase 03 — Consultorio y clínica/centro con checkout

## Objetivo

Crear consultorio o clínica desde la app con confirmación informada e idempotente del pago.

## Checklist

- [ ] Consultorio: KYC + REFEPS aprobado + datos del efector + plan + términos antes del checkout.
- [ ] Clínica/centro: KYC + datos del efector + plan + términos; sin requisito REFEPS para AdminEfector.
- [ ] Mostrar importe, moneda, impuestos, recurrencia, renovación, cancelación y soporte antes de pagar.
- [ ] Definir proveedor antes de aceptar pagos reales; checkout alojado o tokenizado, sin guardar PAN/CVV.
- [ ] Persistir estado del alta y cada intento de pago; idempotencia, reconciliación y webhooks firmados.
- [ ] No activar la licencia ni permisos de pago antes de la confirmación del proveedor.
- [ ] Reanudar errores/rechazos desde el registro persistido sin duplicar efector, usuario o cobro.
- [ ] Crear AdminEfector y rol clínico por separado; consultorio propio requiere ambos dominios.

## Criterio de salida

Pruebas de aprobación, rechazo, timeout, webhook repetido y reintento prueban ausencia de duplicados y de acceso prematuro.
