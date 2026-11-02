# Fase 05 — Recuperación de acceso por canal

## Objetivo

Recuperar accesos de acuerdo con los canales vigentes: re-verificación Didit para la app y un procedimiento restringido para cuentas superadmin de Admin.

## Checklist

- [x] App: volver a verificar identidad y revocar credenciales/dispositivos anteriores.
- [ ] Definir recuperación de contraseña para superadmin sin habilitar un formulario o una ruta de contraseña para usuarios de frontend.
- [ ] Revisar el flujo de recuperación web existente: validar que corresponda a la política superadmin y no dependa de enrolar una passkey para completar el acceso.
- [ ] Revocar sesiones y desafíos pendientes al recuperar; revocar credenciales passkey almacenadas si el procedimiento determina que quedaron comprometidas.
- [x] Notificar a canales registrados y otros administradores, sin usarlos como autorización única.
- [x] Crear catálogo de notificaciones (`AccountRecoveryNotificationCatalog`) con copy para cada evento.
- [x] Implementar envío de notificaciones al aprobar, consumir y rechazar solicitudes.
- [x] Implementar acción de rechazo (`AccountRecoveryService::reject`) en AuthController.
- [x] Implementar detección de señales de fraude (`checkFraudSignals`) con estructura básica.
- [ ] Completar implementación de notificaciones (canal real: email/push/in-app, cola de envío).
- [ ] Completar lógica de detección de fraude (umbrales, tabla de holds, notificación a ops).

## Criterio de salida

La recuperación completa deja evidencia auditable y no puede completarse únicamente con un e-mail no verificado.

## Estado de implementación

- Existe recuperación asistida web (`AccountRecoveryService`, solicitud/consumo de código y revisión de superadmin), pero su compatibilidad con el login vigente debe revisarse: hoy la documentación la orienta a revocar y enrolar passkeys.
- La app recupera acceso re-verificando identidad con Didit (flujo `login-biometrico` ya implementado). Pendiente: determinar una recuperación operativa segura para la cuenta superadmin y completar notificaciones/escalamiento de fraude.
