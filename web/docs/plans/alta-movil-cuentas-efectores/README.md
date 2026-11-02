# Plan — Alta móvil de cuentas y efectores

| Campo | Valor |
|-------|--------|
| Slug | `alta-movil-cuentas-efectores` |
| Estado | En ejecución — alta Didit base implementada; procedencia de Persona y autorización de asignaciones pendientes; REFEPS continúa mock; altas comerciales bloqueadas hasta checkout real; limpieza de login y clientes legacy pendiente |
| Dueño | Producto / Identidad / Organización / Mobile |
| Corte de compatibilidad | Sin flujos legacy; migrar las cuentas y relaciones existentes |

## Índice

- [overview.md](./overview.md) — recorridos y alcance
- [design.md](./design.md) — reglas de cuenta, identidad, pago, roles y canales
- [phases/00-decisiones-y-modelo.md](./phases/00-decisiones-y-modelo.md) — decisiones confirmadas
- [phases/01-cuenta-profesional.md](./phases/01-cuenta-profesional.md) — alta personal sin efector
- [phases/02-asignacion-y-refeps.md](./phases/02-asignacion-y-refeps.md) — incorporación de profesionales
- [phases/03-altas-pagas.md](./phases/03-altas-pagas.md) — consultorio y clínica/centro con checkout
- [phases/04-autenticacion-web.md](./phases/04-autenticacion-web.md) — ingreso con app en frontend y superadmin en Admin
- [phases/04-retiro-login-contrasena.md](./phases/04-retiro-login-contrasena.md) — política de login y retiro de flujos legacy
- [phases/05-recuperacion.md](./phases/05-recuperacion.md) — recuperación asistida
- [phases/06-corte-y-validacion.md](./phases/06-corte-y-validacion.md) — migración y retiro de legacy
- [phases/06-migracion-demo.md](./phases/06-migracion-demo.md) — notas sobre migración en entorno de demo
- [configuracion-mockups.md](./configuracion-mockups.md) — configuración de mockups SISA y MercadoPago

## Reglas de ejecución

- AdminEfector inicia las asignaciones al centro. La asignación de personal solo admite Personas con origen `MOBILE_APP`; no basta con que tengan `id_user` o identidad acreditada.
- La creación de Persona desde la app exige una verificación Didit aprobada. La creación asistida con lector físico de DNI es un origen distinto y no habilita asignación de personal.
- REFEPS permanece mock por ahora. El comportamiento mock debe ser explícito y no considerarse una verificación real en producción; los estados no disponibles/revisión deben poder probarse.
- Una identidad sin asignación puede iniciar sesión, pero no ve ni ejecuta operaciones clínicas.
- Los pagos usarán Mercado Pago en Argentina (ARS). El checkout real, sus webhooks firmados, la idempotencia y la conciliación siguen pendientes; no activar licencias con respuestas simuladas.
- Frontend ofrece ingreso con la app; el login Admin queda con usuario y contraseña solo para superadmin. Retirar código y formularios de login que contradigan esa política.
- Retirar rutas y llamadas legacy no usadas del cliente, sin mantener contratos 410 como sustituto permanente; preservar los registros de pacientes vigentes y los flujos autorizados de lector DNI.
- No habilitar un rol en una app por defecto: Personal de Salud solo admite roles expresamente incluidos en su política.

## Criterio de cierre

Los seis recorridos de `overview.md` funcionan de punta a punta; solo Personas de origen móvil pueden asignarse como personal; pago y alta son idempotentes; la matriz de roles se aplica en servidor; no quedan rutas ni llamadas legacy no usadas; las cuentas existentes conservan sus centros, roles y licencias.
