# Fase 00 — Decisiones y modelo de producto

## Decisiones confirmadas

- [x] Dos opciones: cuenta personal gratuita o creación de un espacio de atención.
- [x] Persona/User se crean o recuperan después de Didit. La cuenta gratuita no recibe un rol profesional ni acceso a Personal de Salud.
- [x] La opción paga unifica consultorio y clínica/centro; no se mantienen los perfiles comerciales anteriores por compatibilidad.
- [x] El titular selecciona sector público o privado; para público elige ministerio y efector oficial SISA.
- [x] No hay un límite artificial de un profesional. 200 AMB es una referencia orientativa, no una restricción de personal.
- [x] Crear identidad o iniciar checkout no concede AdminEfector ni habilita licencia.
- [x] Pago aprobado y conciliado activa licencia y asigna AdminEfector.
- [x] La asignación clínica del titular es posterior y separada de AdminEfector.
- [x] Persona/Paciente solo no puede ingresar a Personal de Salud; la reanudación se inicia por «Crear cuenta» y no utiliza una sesión de paciente de esa app.
- [x] El alta abandonada debe poder recuperarse desde el borrador asociado a la identidad verificada.
- [x] Didit→API se reintenta con `verification_id` temporal local protegido y validado nuevamente por el servidor.

## Decisiones técnicas por cerrar

- [ ] Esquema/estados de borrador, relación con `BillingCheckoutAttempt` y política de versiones.
- [ ] Contrato API autenticado por prueba Didit para crear/leer/actualizar/reanudar borradores sin emitir una sesión de Personal de Salud.
- [ ] Cómo reanudar el checkout en estados `PENDING`, `FAILED`, cancelado o expirado; qué estados permiten editar selección.
- [ ] Estrategia ante borradores/checkouts legacy en curso y tiempo de coexistencia del cliente viejo.
- [ ] Si la primera entrega soporta recuperación cross-device o si se libera solo tras resolver esa capacidad con Didit.
- [ ] Política de expiración/limpieza de borradores abandonados y verificación Didit local.

## Criterio de salida

El contrato no permite obtener permisos por identidad, borrador o checkout; estados de onboarding y checkout son distinguibles; el corte y los intentos legacy están inventariados antes de implementar migraciones destructivas o retirar rutas.
