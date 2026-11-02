# Fase 03 — Consultorio y clínica/centro con checkout

## Objetivo

Habilitar consultorio propio y clínica/centro desde la app con identidad Didit, confirmación informada e idempotente del pago. Las altas permanecen cerradas hasta completar el checkout real y sus controles.

## Checklist

- [x] Definir proveedor de pagos: MercadoPago con estructura básica de integración.
- [x] Crear servicio `MercadoPagoCheckoutService` con métodos createPreference, getPaymentStatus, processWebhook.
- [x] Crear controlador `MercadoPagoWebhookController` para recibir notificaciones de pago.
- [x] Configurar parámetros `mercadoPago` en `common/config/params.php` (sandboxMode, accessToken, etc.).
- [x] Documentar estructura base: código con bloques de integración real comentados, modo sandbox por defecto.
- [ ] Derivar identidad y Persona de la cuenta autenticada creada con Didit; no aceptar datos del cliente para crear Persona/User ni permitir crear Persona sin `verification_id`.
- [ ] Consultorio: cuenta Didit + datos del efector + elección de ejercer como Médico/especialidad + plan + términos antes del checkout. REFEPS permanece mock por ahora.
- [ ] Crear automáticamente la asignación AdminEfector al titular; crear además la asignación clínica de Médico solo si eligió ejercer como tal y cumple la política REFEPS vigente.
- [ ] Clínica/centro: cuenta Didit + datos del efector + sector público/privado + plan + términos; REFEPS no es requisito para que AdminEfector administre.
- [ ] Clínica/centro público y privado comparten recorrido; antes de crear uno público, comprobar que el efector no esté vinculado a una cuenta ministerial.
- [ ] Definir identificador confiable del efector para el control ministerial; no basarse solo en nombre/domicilio ni en el código SISA aleatorio generado actualmente.
- [ ] Después del alta, permitir a AdminEfector definir los servicios del centro y luego asignar personal; no precargar servicios ni personal.
- [ ] Mostrar importe, moneda, impuestos, recurrencia, renovación, cancelación y soporte antes de pagar.
- [ ] Completar integración real con Mercado Pago para Argentina (ARS): checkout alojado/tokenizado, sin guardar PAN/CVV.
- [ ] Persistir estado del alta y cada intento de pago; idempotencia, reconciliación y webhooks firmados.
- [ ] No activar la licencia ni permisos de pago antes de la confirmación del proveedor.
- [ ] Reanudar errores/rechazos desde el registro persistido sin duplicar efector, usuario o cobro.
- [ ] Mantener separadas las responsabilidades: consultorio siempre crea AdminEfector; solo agrega la asignación clínica si el titular elige ser Médico.
- [ ] No reactivar el endpoint de alta mientras `persona/create` permita alta directa y `SignUpInstitutionalEfector` conserve un camino para crear Persona/User sin verificación móvil.
- [ ] Resolver el acceso web de AdminEfector antes de habilitar clínicas: frontend ofrece ingreso con la app y la política actual excluye AdminEfector del acceso móvil.

## Criterio de salida

Pruebas de aprobación, rechazo, timeout, webhook repetido y reintento prueban ausencia de duplicados y de acceso prematuro.

El endpoint legado de alta institucional con pago simulado permanece retirado temporalmente. No se crea efector, licencia ni permiso comercial hasta que el alta use la identidad Didit autenticada y el pago real se confirme mediante una notificación verificada.
