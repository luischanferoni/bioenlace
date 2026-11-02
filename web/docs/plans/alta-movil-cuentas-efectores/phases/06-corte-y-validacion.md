# Fase 06 — Corte sin legacy y validación

## Objetivo

Desplegar el modelo nuevo sin rutas antiguas y sin perder las relaciones/activos de cuentas actuales.

## Checklist

- [x] Evaluar migración de usuarios: asumiendo entorno de demo con pocos usuarios, no hay datos críticos que requieran transformación compleja.
- [x] Preservar tablas existentes (Persona, User, PES, efectores, licencias) sin alteración de esquema.
- [x] Retirar el alta profesional del endpoint legacy `/api/v1/registro/registrar` y conservar el registro de pacientes.
- [x] Retirar login móvil staff con usuario/contraseña y activación por código; el API responde `410 Gone`.
- [x] Retirar el registro genérico de usuario con contraseña; el API responde `410 Gone` y permanece separado el registro de pacientes.
- [ ] Eliminar del cliente móvil las llamadas/helpers legacy de registro genérico, login con credenciales y activación por código; quitar del servidor las rutas/actions retiradas tras confirmar que no tienen consumidores soportados.
- [ ] Retirar o proteger `POST /api/v1/persona/create`; mantener registro de pacientes Didit y alta de paciente asistida con lector DNI como flujos distintos.
- [ ] Añadir a `personas` un campo de procedencia (propuesto `origen_creacion`) con default seguro `LEGACY_UNKNOWN`; marcar nuevos registros en backend como `MOBILE_APP` o `STAFF_DNI_READER` según el flujo real.
- [ ] Filtrar candidatos a personal y validar server-side que toda asignación de personal sea de origen `MOBILE_APP`.
- [ ] Confirmar que la migración no infiere origen con `id_user`, `acredita_identidad` ni referencias Didit sin evidencia suficiente.
- [x] Retirar login y autenticación móvil para AdminEfector y roles no aprobados; también se filtran tokens emitidos antes del cambio cuando se usan desde Personal de Salud.
- [x] Retirar el alta institucional con pago simulado; `POST /api/v1/licencia/registrar-efector` responde `410 Gone` hasta contar con checkout real.
- [ ] Quitar de la vista frontend el formulario y textos legacy de usuario/contraseña y mantener solo ingreso con la app; no ofrecer passkey.
- [ ] Restringir el login del panel Admin a superadmin, manteniendo usuario/contraseña y sin passkey/QR.
- [x] No provisionar licencias/roles por pagos simulados en producción.
- [x] Migrar los JWT de ambas apps móviles desde `SharedPreferences` al almacenamiento seguro de la plataforma, eliminando la copia en texto plano al migrar o leer la sesión.
- [x] Deshabilitar los backups automáticos de Android en ambas apps para evitar restaurar datos cifrados sin sus claves del dispositivo.
- [x] Implementar revocación server-side de sesiones/JWT (`jti` persistido, revocación en logout, rotación al refrescar y validación en el middleware de autenticación).
- [ ] Añadir QA para procedencia de identidad, intentos de asignar Personas no móviles, identidad sin efector, REFEPS mock, ambos checkout, login app/QR y acceso Admin de superadmin.
- [ ] Confirmar observabilidad, límites de abuso y runbook de soporte.

## Criterio de salida

No quedan llamadas/rutas legacy de registro genérico, credenciales móviles ni activación por código en clientes soportados. Se conservan el registro Didit de la app, el registro de pacientes y la alta asistida de pacientes con lector físico. Frontend ofrece ingreso con la app y Admin solo usuario/contraseña para superadmin; las Personas históricas quedan `LEGACY_UNKNOWN` salvo evidencia fiable de origen.
