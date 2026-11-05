# Fase 03 — Consultorio y clínica/centro con checkout

## Objetivo

Habilitar consultorio propio y clínica/centro desde la app con identidad Didit, confirmación informada e idempotente del pago. No habilitar producción hasta configurar y probar las credenciales, el webhook y la cotización comercial.

## Checklist

- [x] Integrar creación real de suscripción mensual en Mercado Pago (`/preapproval`) con checkout hospedado; nunca recibir PAN/CVV.
- [x] Persistir intentos idempotentes, precio de referencia USD, cotización MEP y fecha, neto ARS, IVA y total.
- [x] Firmar/verificar notificaciones con `X-Signature`, recuperar el recurso en Mercado Pago y deduplicar eventos.
- [x] Activar cuenta, licencia, efector y rol AdminEfector solo después del primer pago aprobado.
- [x] Derivar identidad y Persona de la sesión autenticada creada desde la app; el flujo comercial no crea Persona/User.
- [x] Habilitar en Personal de Salud consultorio y clínica/centro; mostrar precio e IVA antes de abrir el checkout hospedado.
- [x] Consultorio: AdminEfector automático, 200 atenciones AMB por mes y un profesional.
- [x] Clínica/centro privado: selección de localidad y volumen mensual por clase.
- [x] Clínica/centro público: exigir código SISA presente y activo en el catálogo local, rechazar afiliación ministerial o cuenta POOL existente, reutilizar el efector oficial y asociarlo al ministerio elegido.
- [ ] Consultorio: elección opcional de ejercer como Médico/especialidad y alta del rol clínico según la política REFEPS mock.
- [ ] Verificar y documentar actualización periódica del catálogo local con el archivo oficial SISA; códigos que no estén cargados se rechazan.
- [ ] Después del alta, permitir a AdminEfector definir los servicios del centro y luego asignar personal; no precargar servicios ni personal.
- [x] Mostrar importe ARS, neto, IVA, referencia USD/MEP y recurrencia antes de autorizar.
- [x] Completar el formulario (sector, efector, volumen, tipos de atención y videollamada) antes de Didit; Didit se ejecuta antes de abrir el checkout.
- [x] Mostrar el precio estimado según el volumen y la modalidad de videollamada elegidos.
- [ ] Configurar credenciales, secreto de webhook, `backUrl`, `notificationUrl` y cotización MEP vigente fuera del repositorio; probar sandbox y conciliación real.
- [x] Cancelar renovación sin corte inmediato; guardar el fin del período pagado y suspender la cuenta cuando vence sin otro pago aprobado.
- [ ] Definir comportamiento ante reembolsos y contracargos.
- [ ] Programar el cron diario `php yii billing-subscription/expire-paid-through`.
- [ ] Reanudar errores/rechazos desde el registro persistido sin duplicar efector, usuario o cobro; probar concurrencia, timeout y reintentos.
- [ ] Mantener separadas las responsabilidades: consultorio siempre crea AdminEfector; solo agrega la asignación clínica si el titular elige ser Médico.
- [x] Exigir sesión autenticada para el endpoint comercial; `SignUpInstitutionalEfector` requiere Persona/User vinculados y no crea identidades manuales.
- [ ] Confirmar que AdminEfector puede autenticarse en la app sin recibir operaciones administrativas/clínicas; su gestión del centro continúa en frontend.

## Criterio de salida

Pruebas sandbox de aprobación, rechazo, timeout, webhook repetido y reintento prueban ausencia de duplicados y de acceso prematuro.

El endpoint comercial requiere identidad móvil autenticada. No se crea efector, licencia ni permiso comercial hasta confirmar el primer pago mediante una notificación verificada. La configuración de producción y las reglas de identificación de efectores públicos siguen siendo bloqueantes.
