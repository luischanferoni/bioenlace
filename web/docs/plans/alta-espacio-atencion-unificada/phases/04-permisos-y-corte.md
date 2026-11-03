# Fase 04 — Permisos móviles y corte de perfiles

## Objetivo

Aplicar la política de acceso en servidor y retirar la división anterior sin romper cuentas y pagos ya existentes.

## Trabajo

- [ ] Ajustar `StaffMobileAccessService` y login para negar Personal de Salud a Persona/Paciente solo; permitir solo roles explícitamente autorizados, incluyendo AdminEfector después de su asignación.
- [ ] Revisar endpoints de login biométrico, bearer JWT, login credenciales y arranque de app para que no haya rutas de bypass por rol dinámico.
- [ ] No ofrecer wizard/home funcional de Personal de Salud a una cuenta sin rol habilitante; debe permanecer en login y usar «Crear cuenta» para onboarding.
- [ ] Crear acceso al borrador basado en verificación Didit separado del mecanismo de sesión/roles de Personal de Salud.
- [ ] Proteger operaciones del asistente de administración por AdminEfector y pertenencia al efector, no solo por ocultamiento de UI.
- [ ] Cambiar endpoints, payloads y tipos de dominio para eliminar `CONSULTORIO`/`CLINICA` como perfiles de alta.
- [ ] Eliminar restricciones de máximo un profesional y valores fijos que solo aplicaban al perfil Consultorio; mantener controles legítimos de catálogo/licencia.
- [ ] Retirar acordeones, modelos y ramas de cliente/API legacy en un corte coordinado, sin ofrecer modo de compatibilidad.
- [ ] Preservar cuentas, licencias, pagos, webhooks y asignaciones preexistentes.

## Criterios de aceptación

- Una cuenta con solo capacidad/rol Paciente no inicia sesión en Personal de Salud por ningún método.
- Usuario sin AdminEfector no accede al asistente administrativo ni a APIs del efector.
- AdminEfector asignado tras pago accede al asistente autorizado; la administración no habilita APIs clínicas.
- No hay contratos activos que requieran elegir `CONSULTORIO` o `CLINICA` en el nuevo recorrido.
