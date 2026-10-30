# Fase 01 — Cuenta profesional personal sin asignación

## Objetivo

Permitir crear una identidad profesional desde la app sin e-mail, efector, REFEPS ni licencia. La cuenta no habilita operaciones clínicas hasta recibir una asignación válida.

## Checklist

- [x] Reemplazar el formulario único de consultorio por la selección de cuenta profesional, consultorio y clínica/centro.
- [x] Retirar el e-mail obligatorio del alta profesional.
- [ ] Crear/recuperar identidad de forma idempotente a partir de verificación aprobada.
- [ ] Persistir estado de alta y permitir reintentar sin duplicar la Persona/User.
- [x] Mostrar estado “pendiente de incorporación por administración” en la app sin asignación.
- [x] Permitir que una identidad sin roles/efector consulte el wizard de sesión, sin acceso a APIs clínicas.
- [x] Añadir prueba unitaria de la política para identidad sin asignación.

Las opciones comerciales se muestran deshabilitadas hasta que exista un checkout real. Se retiró del cliente la tarjeta de prueba fija y su llamada al endpoint; el endpoint legado sigue pendiente de corte y el simulador rechaza cobros en producción.

## Estado

En implementación. La API de identidad ya admite e-mail opcional y reutiliza Persona/User; el cliente ya presenta la opción profesional y muestra el estado sin centro. Siguen pendientes la persistencia de intentos/reanudación y las pruebas de integración del alta completa.
