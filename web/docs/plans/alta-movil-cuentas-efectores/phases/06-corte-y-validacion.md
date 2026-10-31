# Fase 06 — Corte sin legacy y validación

## Objetivo

Desplegar el modelo nuevo sin rutas antiguas y sin perder las relaciones/activos de cuentas actuales.

## Checklist

- [ ] Inventariar y migrar usuarios, Persona, efectores, PES, billing accounts, contratos y permisos.
- [ ] Activar enrolamiento de passkey para identidades web existentes antes de retirar contraseña.
- [x] Retirar el alta profesional del endpoint legacy `/api/v1/registro/registrar` y conservar el registro de pacientes.
- [ ] Retirar login web con contraseña cuando passkeys/QR estén enrolados.
- [x] Retirar login móvil staff con usuario/contraseña y activación por código; el API responde `410 Gone`.
- [x] Retirar el registro genérico de usuario con contraseña; el API responde `410 Gone` y permanece separado el registro de pacientes.
- [x] Retirar login y autenticación móvil para AdminEfector y roles no aprobados; también se filtran tokens emitidos antes del cambio cuando se usan desde Personal de Salud.
- [x] Retirar el alta institucional con pago simulado; `POST /api/v1/licencia/registrar-efector` responde `410 Gone` hasta contar con checkout real.
- [x] No provisionar licencias/roles por pagos simulados en producción.
- [x] Migrar los JWT de ambas apps móviles desde `SharedPreferences` al almacenamiento seguro de la plataforma, eliminando la copia en texto plano al migrar o leer la sesión.
- [x] Deshabilitar los backups automáticos de Android en ambas apps para evitar restaurar datos cifrados sin sus claves del dispositivo.
- [x] Implementar revocación server-side de sesiones/JWT (`jti` persistido, revocación en logout, rotación al refrescar y validación en el middleware de autenticación).
- [ ] Añadir QA para identidad sin efector, incorporación, REFEPS, ambos checkout, passkey, QR y recuperación.
- [ ] Confirmar observabilidad, límites de abuso y runbook de soporte.

## Criterio de salida

No quedan fallbacks legacy activos; los usuarios actuales conservan sus datos y completan enrolamiento antes de quedar sujetos al nuevo método de acceso.
