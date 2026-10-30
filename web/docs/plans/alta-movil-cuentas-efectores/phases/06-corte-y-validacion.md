# Fase 06 — Corte sin legacy y validación

## Objetivo

Desplegar el modelo nuevo sin rutas antiguas y sin perder las relaciones/activos de cuentas actuales.

## Checklist

- [ ] Inventariar y migrar usuarios, Persona, efectores, PES, billing accounts, contratos y permisos.
- [ ] Activar enrolamiento de passkey para identidades web existentes antes de retirar contraseña.
- [x] Retirar el alta profesional del endpoint legacy `/api/v1/registro/registrar` y conservar el registro de pacientes.
- [ ] Retirar login web/mobile con password, código de activación y alta comercial web antigua.
- [ ] Retirar acceso móvil implícito para AdminEfector y roles no aprobados.
- [ ] No provisionar licencias/roles por pagos simulados en producción.
- [ ] Añadir QA para identidad sin efector, incorporación, REFEPS, ambos checkout, passkey, QR y recuperación.
- [ ] Confirmar observabilidad, límites de abuso y runbook de soporte.

## Criterio de salida

No quedan fallbacks legacy activos; los usuarios actuales conservan sus datos y completan enrolamiento antes de quedar sujetos al nuevo método de acceso.
