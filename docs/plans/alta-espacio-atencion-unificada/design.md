# Design — Alta unificada de espacios de atención

## Decisiones de producto

| Tema | Decisión |
|------|----------|
| Opciones iniciales | Cuenta personal gratuita; crear espacio de atención |
| Identidad | Didit desde la app; la API crea o recupera Persona/User |
| Cuenta gratuita | No requiere asignación clínica y no puede entrar a Personal de Salud; las funciones de paciente viven en la app paciente |
| Espacio de atención | Un solo recorrido para consultorio y clínica/centro; sector, efector, servicios y volúmenes determinan la configuración |
| Volumen | Configurable según catálogo por clase; 250 AMB (media jornada) y 500 AMB (jornada completa) son referencias orientativas, no un máximo de personal |
| Sector | El titular selecciona público o privado |
| Sector público | Selección obligatoria de ministerio y efector SISA elegible; conservar controles anti-duplicación/afiliación existentes |
| Pago | Suscripción mensual en ARS por Mercado Pago; el servidor calcula precio, impuestos y snapshot FX |
| AdminEfector | Solo se asigna después del primer pago confirmado |
| Rol clínico | Independiente de AdminEfector; requiere incorporación explícita y validación REFEPS aplicable |
| Asistente | AdminEfector habilitado puede usar el asistente para configurar servicios y asignar personal; no implica acceso clínico automático |
| Compatibilidad | Sin compatibilidad funcional con los perfiles móviles `CONSULTORIO`/`CLINICA`; preservar historial financiero y cuentas existentes |

## Roles y autorización

- La creación de Persona/User no es login a Personal de Salud ni concede rol profesional.
- La app de Personal de Salud debe rechazar cuentas cuyo único acceso sea paciente/identidad. Al abrir la app se presenta login; el CTA «Crear cuenta» inicia o retoma el onboarding sin sesión de paciente.
- La app de pacientes mantiene su comportamiento de paciente; no inferir que el rol/capacidad base de paciente habilita Personal de Salud.
- El pago pendiente, la URL de checkout y cualquier borrador no conceden permisos de efector.
- Tras webhook válido, la asignación PES `AdminEfector` y la activación del entitlement se realizan como una transición atómica e idempotente.
- El titular puede asignarse a sí mismo posteriormente como personal, sujeto a búsqueda/selección permitida, servicio habilitado y REFEPS según reglas actuales. Ser propietario/AdminEfector no equivale a ser profesional.

## Persistencia y transiciones

El borrador comercial server-side se asocia a Persona/User y guarda, como mínimo:

- Estado del onboarding y versión de esquema.
- Sector y datos del efector; ministerio, código SISA y reserva/conflicto cuando aplique.
- Servicios/clases y volúmenes elegidos.
- Datos de contacto requeridos para checkout.
- Identificador idempotente de solicitud, revisión y timestamps.
- Referencia al intento persistido de checkout y sus estados, sin copiar credenciales de pago.

Estados propuestos:

`identity_created` → `draft` → `checkout_pending` → `active`

Salidas recuperables: `abandoned`, `checkout_failed`, `cancelled`, `expired`. Ninguna salida previa a `active` habilita AdminEfector. Webhook repetido, reintento de app o doble envío del cliente no duplica efector, cuenta comercial, entitlement, suscripción ni PES.

El borrador mutable y el snapshot inmutable de checkout son conceptos distintos. Editar el borrador después de crear checkout debe cerrar/reemplazar el intento según el estado del proveedor, nunca cambiar retroactivamente el importe de una suscripción existente.

## Reanudación e identidad

- Antes de crear Persona/User, conservar `verification_id` en almacenamiento seguro móvil hasta confirmación de la API. El servidor verifica con Didit cada reintento; expirar o invalidar el valor local cuando ya no pueda usarse.
- No guardar selfie, imágenes del documento, respuesta cruda de Didit o datos de identidad no requeridos en el cliente.
- Después de crear Persona/User, recuperar borrador tras una nueva verificación Didit desde el CTA «Crear cuenta». La reanudación no usa login biométrico de Personal de Salud ni JWT de rol paciente.
- La recuperación debe verificar vínculo de identidad y devolver solo borradores propios, con control de concurrencia y rate limiting.
- Para un primer hito puede soportarse reanudación en el mismo dispositivo; la recuperación cross-device mediante Didit es el comportamiento objetivo y debe quedar probado antes del cierre.

## Público / privado

- Privado: datos del nuevo efector y localidad; validar duplicados según las reglas vigentes.
- Público: ministerio obligatorio, elección de efector local cargado desde SISA y validación de que sea elegible, no esté afiliado/conflictivo y no tenga una cuenta incompatible.
- Catálogo ausente o desactualizado produce un error explícito y recuperable, no la creación de un efector público ficticio ni el bypass de ministerio.
- La configuración comercial y los precios se calculan con el mismo catálogo de servicios/volúmenes del backend y checkout.

## Seguridad y auditoría

- Autoridad de rol y estado de pago permanece en backend; los parámetros del cliente nunca prueban pago o elegibilidad.
- Checkout hospedado Mercado Pago; no almacenar PAN/CVV.
- Verificar firma del webhook y consultar el recurso al proveedor antes de activar.
- Registrar transiciones, actor/Persona, referencia del proveedor, clave idempotente y causa de fallos sin persistir secretos ni payloads sensibles innecesarios.
- Las respuestas de recuperación no deben enumerar documentos/Personas ni revelar si una identidad existe antes de una prueba Didit aprobada.

## Corte y datos existentes

- El cliente nuevo deja de enviar perfiles `CONSULTORIO` y `CLINICA`; API y backend usan un contrato unificado.
- No hace falta soportar solicitudes comerciales legacy como entrada activa del nuevo checkout.
- No borrar licencias, pagos, suscripciones, webhooks ni asignaciones existentes.
- Inventariar checkouts pendientes y borradores antiguos. Definir si se dejan completar por el flujo de checkout actual, se migran a borrador unificado o se cancelan/reembolsan antes del despliegue. No invalidar una suscripción ya creada.
- Desplegar la migración de esquema de forma compatible con lectores actuales; retirar ramas legacy solo después del corte coordinado de clientes y API.
