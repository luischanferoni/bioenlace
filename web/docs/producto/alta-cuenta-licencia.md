# Alta de cuenta y licencia

## Modelo acordado (implementación en curso)

La app **Personal de Salud** es el canal de creación de cuenta. La persona verifica su identidad con documento y selfie. Esa verificación acredita identidad, pero no otorga por sí sola habilitación profesional, asignación a un centro, licencia ni acceso clínico.

Hay tres recorridos:

1. **Cuenta profesional personal:** gratuita y sin efector. El AdminEfector del centro incorpora a la persona buscándola en Bioenlace. Hasta entonces no puede ver ni realizar operaciones clínicas.
2. **Consultorio propio:** recorrido pago. REFEPS debe estar confirmado antes de iniciar el checkout. La gestión del consultorio y el rol clínico son permisos separados.
3. **Clínica o centro:** recorrido pago. Crea la organización y el acceso de AdminEfector; REFEPS no es requisito para administrar. Si esa persona también atiende, AdminEfector debe asignarla y se verifica REFEPS para el rol clínico.

No se solicita e-mail para crear identidad. El modelo objetivo reemplaza los códigos por correo y las contraseñas por passkeys y aprobación QR según el canal habilitado; el retiro de los flujos de autenticación heredados todavía está pendiente. AdminEfector es web-only por ahora; Personal de Salud permite únicamente roles habilitados explícitamente. Los roles administrativos y futuros son web-only por defecto.

## Estado actual de implementación

- La app permite elegir el recorrido profesional personal y crear una cuenta tras Didit, sin pedir e-mail.
- Consultorio y clínica/centro aparecen como opciones, pero permanecen deshabilitados hasta implementar checkout real, condiciones de venta e intentos persistidos.
- Una cuenta sin efector puede abrir el wizard informativo, sin obtener permisos clínicos.
- AdminEfector fue retirado de la allowlist de Personal de Salud.
- El simulador no se considera un pago válido y queda bloqueado en producción. No usarlo para contratar o activar licencias.
- El endpoint legado de alta comercial sigue presente y debe sustituirse o retirarse al implementar checkout real; no está habilitado como recorrido nuevo.
- El login web y otros puntos legacy de alta/autenticación aún requieren el corte de la Fase 6; no se consideran parte del flujo nuevo.

## REFEPS y asignación

AdminEfector inicia la incorporación de una persona ya registrada, incluso si se incorpora a sí mismo a un servicio clínico. El servidor ahora exige confirmación REFEPS y coincidencia exacta del documento devuelto antes de crear una asignación clínica. Distingue coincidencia confirmada, no encontrado, indisponibilidad temporal y revisión manual; ninguna respuesta negativa, ambigua o ausente concede el rol.

La integración todavía no está lista para producción: este repositorio no incluye el cliente SISA/REFEPS y la única referencia existente consulta el alcance de Santiago. Hasta conectar un proveedor con cobertura adecuada y un circuito de revisión, las nuevas asignaciones clínicas fallan de forma segura; las asignaciones AdminEfector no requieren esa consulta.

La identidad profesional sin asignación no puede elegir un centro ni sumarse por cuenta propia. La licencia del efector cubre a los profesionales según el plan, no se compra una segunda licencia por cada profesional asignado.

El endpoint legado `POST /api/v1/registro/registrar` ya no acepta `tipo=medico`; devuelve `410 Gone` y dirige a la creación de cuenta desde Personal de Salud. El registro de pacientes continúa disponible en ese endpoint. Si falla la creación luego de una verificación aprobada, la app puede reintentar usando la misma verificación; la API reutiliza la identidad existente.

El acceso a Personal de Salud se valida en el servidor: solo los roles clínicos habilitados o una identidad profesional todavía sin asignación pueden ingresar. AdminEfector, Administrativo y facturista usan la web; los endpoints móviles legacy de usuario/contraseña y activación por código responden `410 Gone`.

El alta institucional por `POST /api/v1/licencia/registrar-efector` también responde `410 Gone`: no se crean clínicas, centros ni licencias usando pagos simulados.

## Pagos y licencia

Antes del checkout se informarán importe, moneda, impuestos, alcance del plan, recurrencia, renovación, cancelación y soporte. Los datos de tarjeta se procesarán mediante checkout alojado o tokenización; Bioenlace no guardará PAN ni CVV. El alta, los intentos y la conciliación deben ser idempotentes. Una licencia no se activa hasta la confirmación del proveedor.

No hay actualmente una pasarela comercial real configurada para estos recorridos. El proveedor, los precios y las condiciones de suscripción deben definirse antes de habilitar los caminos pagos.

## Acceso y recuperación

- Web: passkeys/WebAuthn como autenticación principal.
- App y web: aprobación de un desafío QR desde la app solo para roles habilitados en ambos canales.
- App: recuperación mediante nueva verificación de identidad.
- Web-only: recuperación asistida con re-verificación, auditoría y revocación de credenciales/sesiones anteriores.
- No mantener login de contraseña o códigos como fallback legacy. La migración preserva las relaciones y activos existentes y exige enrolamiento seguro.

## Ministerios

El alta de AdminMinisterio continúa siendo asistida y requiere aprobación humana. No está incluida en el alta comercial self-service de clínicas, centros o consultorios.

## Demo sandbox

Desde el sitio institucional, CTA **Probar demo** pide captcha y un código de un solo uso (`POST /api/v1/licencia/demo-acceso`). El perfil elige médico (`demo_m_*`, AMB), médico odontología (`demo_m_*`, AMB, servicio ODONTOLOGIA), enfermería (`demo_e_*`, EMER) o administrativo (`demo_a_*`, EMER, ingreso a guardia). La especialidad del médico (subrol) se toma de la tabla `servicios`: el perfil `odontologia` no es un rol RBAC distinto, es un médico con `servicio = ODONTOLOGIA`. `/site/demo-entrar` solo consume el código y hace login (nunca la cuenta legacy `medico_med_general_*`). Se borran al cerrar sesión o al vencer el TTL.

Desde la app **Personal de Salud**, **Probar demo** lista los perfiles staff (`GET …/demo-perfiles`) y llama `POST /api/v1/licencia/demo-acceso-mobile` con el `role` elegido (médico AMB, médico odontología AMB, enfermería o administrativo EMER). Flag `demo_sandbox_habilitado` + knobs en `demo_sandbox` (params), incluido `require_captcha` (web) y `require_captcha_mobile` (app, off por defecto).

## Relación con el resto

- Modelo de pool: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md)
- Decisión: [onboarding-comercial-self-service.md](../decisions/onboarding-comercial-self-service.md)
- Admin: Licencias / Contratos + Solicitudes alta licencia
