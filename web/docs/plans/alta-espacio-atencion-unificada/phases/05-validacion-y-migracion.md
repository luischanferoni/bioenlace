# Fase 05 — QA, estados legacy y liberación

## Objetivo

Liberar el nuevo onboarding sin pérdida de pagos ni permisos inconsistentes.

## Matriz de pruebas

- [ ] Identidad nueva; identidad existente; Didit cancelado, pendiente, rechazado y no disponible.
- [ ] Didit aprobado + timeout API; retry sequential/concurrent; cuenta creada pero respuesta perdida.
- [ ] Guardado, edición, cierre y recuperación de borrador en mismo y diferente dispositivo.
- [ ] Sector privado y público; ministerio ausente/inactivo; efector SISA inexistente, inactivo, afiliado o reservado por otro checkout.
- [ ] Cotización ausente/vencida, volumen por clase, precio e impuestos; modificación de plan con intento pendiente.
- [ ] Timeout Mercado Pago antes/después de crear suscripción; rechazo, cancelación, expiración y callback repetido.
- [ ] Pago aprobado seguido de error en provisionamiento; retry idempotente de activación y rol.
- [ ] Acceso móvil: Persona/Paciente solo denegado, AdminEfector tras pago permitido, clínico según asignación; verificar JWT/API y navegación real.
- [ ] El titular AdminEfector se incorpora como profesional por el recorrido clínico independiente.

## Transición de datos

- [ ] Inventariar intentos y suscripciones `CONSULTORIO`/`CLINICA` pendientes, fallidas, activas y expiradas.
- [ ] Mantener checkout/subscripción ya creados y cargos existentes; no recrear cobros.
- [ ] Para intento sin suscripción: definir migración a borrador unificado o cierre con comunicación al usuario.
- [ ] Asegurar que cambiar contrato de alta no altera importes, tasa FX, IVA ni servicios de pagos ya persistidos.
- [ ] Desplegar cambios de esquema de forma segura; respaldar y auditar transición antes de retirar código antiguo.
- [ ] Retirar ramas legacy después de verificar clientes soportados y rutas de despliegue; sin fallback de perfiles viejos.

## Criterio de salida

Pruebas de estados normales, fallos y reintentos pasan en backend y móvil; sandbox Mercado Pago confirma activación post-pago; migración de pendientes queda conciliada; monitoreo detecta borradores estancados, webhooks fallidos y fallos de provisión; ninguna cuenta obtiene acceso antes de su rol habilitante.
