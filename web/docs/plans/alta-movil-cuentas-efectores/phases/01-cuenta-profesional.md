# Fase 01 — Cuenta profesional personal sin asignación

## Objetivo

Permitir crear una identidad profesional desde la app sin e-mail, efector, REFEPS ni licencia. La cuenta no habilita operaciones clínicas hasta recibir una asignación válida.

## Checklist

- [x] Reemplazar el formulario único de consultorio por la selección de cuenta profesional, consultorio y clínica/centro.
- [x] Retirar el e-mail obligatorio del alta profesional.
- [x] Crear/recuperar identidad de forma idempotente a partir de verificación aprobada.
- [ ] Guardar procedencia `MOBILE_APP` únicamente al completar el flujo aprobado desde Personal de Salud; no aceptar el valor desde el cliente.
- [ ] Verificar que todo flujo de alta de cuenta desde Personal de Salud marque `MOBILE_APP` solo después de Didit aprobado; no aceptar el origen desde el cliente.
- [x] Persistir intentos por hash de la verificación, con estado, contador y referencias de Persona/User; sin guardar el identificador ni la respuesta cruda.
- [x] Hacer atómica la persistencia de Persona + User para evitar cuentas parciales.
- [x] Permitir reintentar la creación desde la app reutilizando la verificación aprobada.
- [x] Mostrar estado “pendiente de incorporación por administración” en la app sin asignación.
- [x] Permitir que una identidad sin roles/efector consulte el wizard de sesión, sin acceso a APIs clínicas.
- [x] Añadir prueba unitaria de la política para identidad sin asignación.

Las opciones comerciales abren el flujo autenticado de alta y checkout. La identidad Didit se crea primero; no se envían datos civiles para crear Persona/User desde el alta comercial.

## Estado

La API exige aprobación explícita de Didit, conserva el estado resumido de cada verificación y el alta en una tabla idempotente, y persiste Persona/User en una transacción. Si falla la creación, la app permite reintentar sin repetir la verificación mientras el flujo siga abierto. La creación es idempotente para reintentos secuenciales. Falta persistir la procedencia verificable y validar la integración en una base con las extensiones PHP requeridas por Codeception.
