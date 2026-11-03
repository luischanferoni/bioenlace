# Fase 04 — Permisos móviles y corte de perfiles

## Objetivo

Aplicar la política de acceso en servidor y retirar la división anterior sin romper cuentas y pagos ya existentes.

## Trabajo

- [x] Ajustar `StaffMobileAccessService` y login para negar Personal de Salud a Persona/Paciente solo; permitir solo roles explícitamente autorizados, incluyendo AdminEfector después de su asignación.
- [x] Revisar endpoints de login biométrico, bearer JWT, login credenciales y arranque de app para que no haya rutas de bypass por rol dinámico. (`AuthController::actionLoginBiometrico`, `JsonHttpBearerAuth`, `login-credenciales` retirado con 410.)
- [x] No ofrecer wizard/home funcional de Personal de Salud a una cuenta sin rol habilitante; debe permanecer en login y usar «Crear cuenta» para onboarding. (`BearerSessionAuth` interpreta `mobile_role_unavailable` como sesión no válida para esta app y limpia la sesión al arrancar.)
- [x] Crear acceso al borrador basado en verificación Didit separado del mecanismo de sesión/roles de Personal de Salud. (`guardar-borrador`, `registrar-efector-didit`, `estado-checkout-didit`, `cancelar-suscripcion-didit` sin sesión de Personal de Salud.)
- [x] Exponer en móvil los flows para el AdminEfector con la misma autorización que en web (rol AdminEfector y pertenencia al efector, no solo ocultamiento de UI). (El asistente móvil reutiliza el backend; el panel de inicio muestra los KPIs de gestión.)
- [x] Cambiar endpoints, payloads y tipos de dominio para eliminar `CONSULTORIO`/`CLINICA` como perfiles de alta. (`prepareDraft` rechaza los perfiles legacy; solo se aceptan al conciliar intentos ya persistidos.)
- [x] Eliminar restricciones de máximo un profesional y valores fijos que solo aplicaban al perfil Consultorio; mantener controles legítimos de catálogo/licencia.
- [x] Retirar acordeones, modelos y ramas de cliente/API legacy en un corte coordinado, sin ofrecer modo de compatibilidad. (Los clientes ya no envían esos perfiles; la única rama restante es la defensiva para conciliar intentos ya persistidos.)
- [x] Preservar cuentas, licencias, pagos, webhooks y asignaciones preexistentes. (El corte solo cambia el contrato de alta; no borra ni recrea datos comerciales.)

## Criterios de aceptación

- Una cuenta con solo capacidad/rol Paciente no inicia sesión en Personal de Salud por ningún método.
- Usuario sin AdminEfector no accede a los flows para el AdminEfector ni a APIs del efector.
- AdminEfector asignado tras pago accede al asistente autorizado; la administración no habilita APIs clínicas.
- No hay contratos activos que requieran elegir `CONSULTORIO` o `CLINICA` en el nuevo recorrido.
