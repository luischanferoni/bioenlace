# Alta de cuenta y licencia

## Modelo acordado (implementación en curso)

La cuenta se crea por dos canales: la app **Personal de Salud** (identidad con documento y selfie) y la **web con el teléfono** (QR + código; la verificación ocurre en el teléfono). En ambos casos la verificación acredita identidad, pero no otorga por sí sola habilitación profesional, asignación a un centro, licencia ni acceso clínico.

Hay tres recorridos:

1. **Cuenta profesional personal:** gratuita y sin efector. El AdminEfector del centro incorpora a la persona buscándola en Bioenlace. Hasta entonces no puede ver ni realizar operaciones clínicas.
2. **Consultorio propio:** recorrido pago. REFEPS debe estar confirmado antes de iniciar el checkout. La gestión del consultorio y el rol clínico son permisos separados.
3. **Clínica o centro:** recorrido pago. Crea la organización y el acceso de AdminEfector; REFEPS no es requisito para administrar. Si esa persona también atiende, AdminEfector debe asignarla y se verifica REFEPS para el rol clínico.

No se solicita e-mail para crear identidad. El modelo objetivo reemplaza los códigos por correo y las contraseñas por passkeys y aprobación QR según el canal habilitado; el retiro de los flujos de autenticación heredados todavía está pendiente. AdminEfector es web-only por ahora; Personal de Salud permite únicamente roles habilitados explícitamente. Los roles administrativos y futuros son web-only por defecto.

## Estado actual de implementación

- La app permite elegir el recorrido profesional personal y crear una cuenta tras Didit, sin pedir e-mail.
- Consultorio y clínica/centro tienen checkout real de Mercado Pago e intentos persistidos; solo operan en entornos con credenciales configuradas (sin ellas el checkout responde `503`).
- Una cuenta sin efector puede abrir el wizard informativo, sin obtener permisos clínicos.
- AdminEfector fue retirado de la allowlist de Personal de Salud.
- El simulador no se considera un pago válido y queda bloqueado en producción. No usarlo para contratar o activar licencias.
- El endpoint legado de alta comercial sigue presente y debe sustituirse o retirarse al implementar checkout real; no está habilitado como recorrido nuevo.
- El login web y otros puntos legacy de alta/autenticación aún requieren el corte de la Fase 6; no se consideran parte del flujo nuevo.

## Alta desde la web con el teléfono

En `/alta` la computadora no pide cámara ni documentos. La persona elige el centro de salud, ve un código QR y la pantalla queda esperando.

1. Escanea el QR con la cámara del teléfono; se abre una página temporal.
2. La computadora muestra un **código de emparejamiento** y la persona lo escribe en el teléfono. Ese paso prueba que quien opera el teléfono ve la pantalla de la computadora: un QR fotografiado a distancia no alcanza para completar el alta.
3. En el teléfono se ejecuta la verificación de identidad de Didit. Al aprobarse, la computadora crea la cuenta con el centro elegido y registra la solicitud de incorporación.

La cuenta queda igual que en la app: perfil Paciente y solicitud pendiente, sin rol clínico hasta que un administrador del centro lo asigne. El alta no abre sesión en la web.

Nada del alta se guarda en el navegador: lo transitorio vive en el vínculo (token y código hasheados, expiración corta) y lo persistente en el borrador del servidor.

## REFEPS y asignación

AdminEfector inicia la incorporación de una persona ya registrada, incluso si se incorpora a sí mismo a un servicio clínico. El servidor ahora exige confirmación REFEPS y coincidencia exacta del documento devuelto antes de crear una asignación clínica. Distingue coincidencia confirmada, no encontrado, indisponibilidad temporal y revisión manual; ninguna respuesta negativa, ambigua o ausente concede el rol.

La integración todavía no está lista para producción: este repositorio no incluye el cliente SISA/REFEPS y la única referencia existente consulta el alcance de Santiago. Hasta conectar un proveedor con cobertura adecuada y un circuito de revisión, las nuevas asignaciones clínicas fallan de forma segura; las asignaciones AdminEfector no requieren esa consulta.

La identidad profesional sin asignación no puede elegir un centro ni sumarse por cuenta propia. La licencia del efector cubre a los profesionales según el plan, no se compra una segunda licencia por cada profesional asignado.

El endpoint legado `POST /api/v1/registro/registrar` ya no acepta `tipo=medico`; devuelve `410 Gone` y dirige a la creación de cuenta desde Personal de Salud. El registro de pacientes continúa disponible en ese endpoint. Si falla la creación luego de una verificación aprobada, la app puede reintentar usando la misma verificación; la API reutiliza la identidad existente.

El acceso a Personal de Salud se valida en el servidor: solo los roles clínicos habilitados o una identidad profesional todavía sin asignación pueden ingresar. AdminEfector, Administrativo y facturista usan la web; los endpoints móviles legacy de usuario/contraseña y activación por código responden `410 Gone`.

El alta institucional por `POST /api/v1/licencia/registrar-efector` también responde `410 Gone`: no se crean clínicas, centros ni licencias usando pagos simulados.

## Pagos y licencia

Antes del checkout se informarán importe, moneda, impuestos, alcance del plan, recurrencia, renovación, cancelación y soporte. Los datos de tarjeta se procesarán mediante checkout alojado o tokenización; Bioenlace no guardará PAN ni CVV. El alta, los intentos y la conciliación deben ser idempotentes. Una licencia no se activa hasta la confirmación del proveedor.

El proveedor es **Mercado Pago con Checkout Pro**: suscripción mensual sin plan asociado, notificaciones firmadas con `X-Signature`, intentos idempotentes, cancelación sin corte inmediato y suspensión automática al vencer el período pagado. La integración está implementada; falta cargar credenciales productivas, el secreto de webhook y la cotización vigente, y completar la prueba end-to-end en sandbox. Detalle operativo: [mercado-pago-checkout-pro.md](../operacion/mercado-pago-checkout-pro.md).

## Acceso y recuperación

- **Web:** tres caminos, ninguno con costo por uso. El QR de login abre `/ingresar/<challenge>`, que muestra el navegador y la hora del pedido. Desde ahí se entra aprobando en la app (con la biometría local del teléfono), con la passkey del dispositivo o con recuperación asistida. Detalle: [ingreso-web-app-passkey-recuperacion.md](../decisions/ingreso-web-app-passkey-recuperacion.md).
- **App:** biometría local para desbloquear la sesión guardada; la verificación de identidad se usa sólo al crear la cuenta o al recuperar el acceso en un dispositivo nuevo.
- **Recuperación asistida:** aprobación humana, código de un solo uso, revocación de credenciales y sesiones, y enrolamiento de una passkey nueva.
- No se mantiene login de contraseña ni códigos por correo. Las sesiones web son cortas: no hay "recordar este navegador" porque se comparten computadoras.

## Aviso de pago

Cuando se confirma el primer pago de un espacio de atención se envía un aviso al e-mail confirmado del titular: es el único canal que no depende de que haya instalado la app. El copy vive en un catálogo del dominio de facturación y el envío no puede revertir ni demorar el alta: se dispara después de confirmada la operación y un fallo sólo queda registrado.

Por eso el alta paga exige un e-mail real, de la cuenta y confirmado antes de iniciar el checkout. La regla está implementada del lado del servidor y se habilita por entorno (`signupRequireConfirmedEmail`), para no bloquear ventas mientras el envío de e-mails no esté verificado.

## Ministerios

El alta de AdminMinisterio continúa siendo asistida y requiere aprobación humana. No está incluida en el alta comercial self-service de clínicas, centros o consultorios.

## Demo sandbox

Desde el sitio institucional, CTA **Probar demo** pide captcha y un código de un solo uso (`POST /api/v1/licencia/demo-acceso`). El perfil elige médico (`demo_m_*`, AMB), médico odontología (`demo_m_*`, AMB, servicio ODONTOLOGIA), enfermería (`demo_e_*`, EMER) o administrativo (`demo_a_*`, EMER, ingreso a guardia). La especialidad del médico (subrol) se toma de la tabla `servicios`: el perfil `odontologia` no es un rol RBAC distinto, es un médico con `servicio = ODONTOLOGIA`. `/site/demo-entrar` solo consume el código y hace login (nunca la cuenta legacy `medico_med_general_*`). Se borran al cerrar sesión o al vencer el TTL.

Desde la app **Personal de Salud**, **Probar demo** lista los perfiles staff (`GET …/demo-perfiles`) y llama `POST /api/v1/licencia/demo-acceso-mobile` con el `role` elegido (médico AMB, médico odontología AMB, enfermería o administrativo EMER). Flag `demo_sandbox_habilitado` + knobs en `demo_sandbox` (params), incluido `require_captcha` (web) y `require_captcha_mobile` (app, off por defecto).

## Pendientes del canal web

- **Formulario de alta paga en la web** (consultorio y clínica/centro). Con él llegan el autoguardado del borrador por paso, la reanudación en el paso donde quedó y la pantalla de estado del alta (en proceso, habilitado, cancelado, vencido).
- **Enrolamiento de passkey** ofrecido al terminar el primer ingreso (la pantalla de gestión ya existe).
- **Aviso de pago:** registrar el envío por pago y encolar los reintentos. Hoy se dispara una vez al activar la licencia.
- **Encender `signupRequireConfirmedEmail`** después de verificar el envío de e-mails del entorno; mientras esté apagado, el checkout no exige la confirmación.
- **Universal Link / App Link** en lugar del esquema propio, para evitar el diálogo del navegador.
- **Tests de integración con base:** aprobación desde la app, desafío de otra sesión, passkey revocada.

## Relación con el resto

- Modelo de pool: [matriz-argentina-modulos-precios.md](../negocio/modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md)
- Decisión: [onboarding-comercial-self-service.md](../decisions/onboarding-comercial-self-service.md)
- Acceso web: [ingreso-web-app-passkey-recuperacion.md](../decisions/ingreso-web-app-passkey-recuperacion.md)
- Admin: Licencias / Contratos + Solicitudes alta licencia
