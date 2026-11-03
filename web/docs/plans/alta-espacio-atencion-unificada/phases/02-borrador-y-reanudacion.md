# Fase 02 — Borrador e idempotencia de identidad

## Objetivo

Sobrevivir a cierres, pérdida de conectividad y fallos entre Didit, identidad, configuración comercial y checkout, sin duplicados ni acceso prematuro.

## Trabajo

- [x] Persistir borrador comercial server-side ligado a Persona/User con estado, sector, efector, ministerio/SISA, plan, contacto y versión.
- [x] Proveer operaciones de guardar, consultar, actualizar y recuperar borrador con autorización basada en nueva verificación Didit, no en sesión de paciente de Personal de Salud.
- [x] Hacer idempotente y seguro ante concurrencia el alta de Persona/User y la asociación `verification_id` aprobada; asegurar recuperación de transacciones/estados parciales.
- [x] En móvil, guardar `verification_id` aprobado en almacenamiento seguro hasta confirmación de creación; reintentar la API antes de lanzar una nueva verificación.
- [x] Borrar/invalidar el dato local al confirmar cuenta, al expirar Didit o cuando la API indique que no es reutilizable.
- [x] Tras cuenta creada, mantener la app en login si no tiene rol habilitante. El CTA «Crear cuenta» reejecuta Didit y recupera el borrador correspondiente.
- [x] Restaurar el paso y datos desde servidor después de reintento; usar almacenamiento local solo como optimización.
- [x] Recuperar borrador en otro dispositivo tras Didit y devolver únicamente el borrador de esa identidad.
- [x] Añadir expiración/limpieza con retención explícita; conservar la auditoría comercial necesaria. (`CommercialSignupDraftService`: retención de 30 días; los borradores `DRAFT` vencidos pasan a `EXPIRED` sin borrar la fila de auditoría.)

## Criterios de aceptación

- Si Didit aprobó y falló la llamada de crear cuenta, el reintento no obliga a iniciar Didit de nuevo mientras la verificación pueda reutilizarse.
- Repetir llamadas concurrentes no crea Persona/User duplicados ni deja referencias cruzadas.
- Tras cerrar la app después de cualquier paso, el titular retoma el estado server-side desde «Crear cuenta».
- Persona existente sin rol profesional no obtiene token/login de Personal de Salud al recuperar el borrador.
- Una nueva verificación Didit en otro dispositivo recupera la misma Persona/User y el borrador, sin enumeración de identidades.
