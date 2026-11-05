# Alta de cuentas y onboarding comercial

## Contexto

El alta debe separar identidad verificada, habilitación profesional, asignación a una organización, licencia paga y canales de acceso. Una cuenta creada no debe implicar que la persona pueda operar clínicamente ni que una organización haya pagado una licencia.

## Decisión

1. Las altas de identidad y de organización comienzan en la app Personal de Salud, con verificación de identidad mediante documento y selfie. El e-mail no es requisito para crear la cuenta ni es un factor de autenticación.
2. Se ofrecen tres recorridos:
   - **Cuenta profesional personal:** sin pago y sin efector. La persona no puede operar hasta que un AdminEfector la encuentre y la asigne.
   - **Consultorio propio:** pago; requiere REFEPS confirmado antes del checkout y separa administración del consultorio de la autorización clínica.
   - **Clínica o centro:** pago; crea el acceso de AdminEfector, sin exigir REFEPS para administrar.
3. AdminEfector es quien inicia toda asignación de personas a un centro, incluso su propia incorporación clínica. Un profesional no se suma a un centro por self-service.
4. REFEPS se consulta al asignar un rol profesional y al contratar consultorio propio. Resultado no encontrado, servicio no disponible y revisión manual son estados diferentes; un timeout no equivale a rechazo.
5. AdminEfector es web-only en esta etapa. Los roles administrativos y futuros son web-only por defecto. Solo roles expresamente habilitados pueden usar Personal de Salud.
6. El acceso web usará passkeys. La aprobación QR desde la app se reserva a personas con roles habilitados en ambos canales. La recuperación de cuenta no depende únicamente de e-mail; para web-only será asistida y requerirá re-verificación de identidad.
7. El alta y cada intento de pago se guardan para reanudar fallos sin duplicar usuario, efector, licencia o cobro. El checkout debe mostrar precio, moneda, impuestos, recurrencia, renovación y cancelación.
8. No se conserva compatibilidad con los flujos legacy de contraseña, códigos ni altas comerciales web. La transición preserva identidades, asignaciones, contratos y licencias.

## Alternativas descartadas

- Hacer que la identidad profesional cree automáticamente un efector o una asignación PES.
- Permitir que el profesional solicite o se sume directamente a un centro.
- Exigir REFEPS a AdminEfector para iniciar una clínica/centro.
- Aceptar una aprobación del simulador como un pago real.
- Mantener contraseña como fallback permanente de passkeys o usar un e-mail no verificado como recuperación suficiente.

## Consecuencias

- La app presenta tres caminos, pero no ofrece los dos recorridos pagos hasta contar con checkout real y persistencia idempotente.
- El wizard móvil puede informar que una cuenta sin centro espera la incorporación del AdminEfector; esta consulta no concede capacidades clínicas.
- La API móvil aplica una allowlist explícita de roles y bloquea AdminEfector en la app.
- El login web, la recuperación, la migración de cuentas actuales y la integración del proveedor de pago forman parte del mismo plan de corte y no pueden resolverse dejando una ruta legacy activa.
- El simulador de pago queda limitado a entornos no productivos. La pasarela real y sus políticas comerciales aún deben seleccionarse.
