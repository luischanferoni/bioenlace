# Plan — Alta móvil de cuentas y efectores

| Campo | Valor |
|-------|--------|
| Slug | `alta-movil-cuentas-efectores` |
| Estado | En ejecución — Fase 1 completa; Fase 2 parcial (mockup SISA implementado, auditoría pendiente); Fase 3 estructura base MercadoPago creada (integración completa pendiente); Fases 4 y 5 implementadas a nivel servidor/web (passkeys, QR, recuperación asistida); Fase 6 parcial (migración simplificada por entorno demo, JWT móvil seguro, backups Android deshabilitados y revocación server-side); checkout completo pendiente |
| Dueño | Producto / Identidad / Organización / Mobile |
| Corte de compatibilidad | Sin flujos legacy; migrar las cuentas y relaciones existentes |

## Índice

- [overview.md](./overview.md) — recorridos y alcance
- [design.md](./design.md) — reglas de cuenta, identidad, pago, roles y canales
- [phases/00-decisiones-y-modelo.md](./phases/00-decisiones-y-modelo.md) — decisiones confirmadas
- [phases/01-cuenta-profesional.md](./phases/01-cuenta-profesional.md) — alta personal sin efector
- [phases/02-asignacion-y-refeps.md](./phases/02-asignacion-y-refeps.md) — incorporación de profesionales
- [phases/03-altas-pagas.md](./phases/03-altas-pagas.md) — consultorio y clínica/centro con checkout
- [phases/04-autenticacion-web.md](./phases/04-autenticacion-web.md) — passkeys y aprobación QR
- [phases/05-recuperacion.md](./phases/05-recuperacion.md) — recuperación asistida
- [phases/06-corte-y-validacion.md](./phases/06-corte-y-validacion.md) — migración y retiro de legacy
- [phases/06-migracion-demo.md](./phases/06-migracion-demo.md) — notas sobre migración en entorno de demo
- [configuracion-mockups.md](./configuracion-mockups.md) — configuración de mockups SISA y MercadoPago

## Reglas de ejecución

- AdminEfector inicia todas las asignaciones a un centro, incluso su propia asignación clínica.
- REFEPS se verifica al asignar un rol profesional y antes de vender un consultorio propio; no es requisito para crear la identidad ni para administrar un centro.
- La integración REFEPS usa el componente Yii `sisa` (`frontend\components\apis\Sisa`) con política fail-closed. En modo mockup (`sisa.mockMode=true` en params), responde siempre OK para desarrollo. Para producción, descomentar el bloque de conexión HTTP y configurar credenciales en params-local.php.
- Una identidad sin asignación puede iniciar sesión, pero no ve ni ejecuta operaciones clínicas.
- Los pagos usan MercadoPago como proveedor con estructura base implementada (`MercadoPagoCheckoutService`, `MercadoPagoWebhookController`). En modo sandbox (`mercadoPago.sandboxMode=true`), simula preferencias de pago. Para producción, completar la integración real con webhooks firmados, idempotencia y conciliación.
- No mantener login de contraseña, activación por código ni alta comercial web como rutas legacy; preservar datos y relaciones existentes durante la transición.
- No habilitar un rol en una app por defecto: Personal de Salud solo admite roles expresamente incluidos en su política.

## Criterio de cierre

Los seis recorridos de `overview.md` funcionan de punta a punta; pago y alta son idempotentes; la matriz de roles se aplica en servidor; no hay una ruta legacy de autenticación o alta activa; las cuentas existentes pueden registrar una passkey sin perder sus centros, roles o licencias.
