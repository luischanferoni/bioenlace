# Plan — Alta de cuenta móvil con identidad Didit

| Campo | Valor |
|-------|-------|
| Slug | `onboarding-mobile-didit` |
| Estado | **En construcción — fase 0/1** |
| Dominio | Organization + Person/Identity |
| Objetivo | Reemplazar el alta web self-service por cuentas de identidad (Didit) creadas en la app Personal de Salud, con el efector como capa posterior y login biométrico local |

## Principio

Cualquier usuario es antes una **Persona verificada con Didit**; los roles (`AdminEfector`, `AdminMinisterio`, profesional) y los efectores son capas que se agregan después sobre la misma Persona.

No hay contraseñas, ni código de activación, ni magic link de login. El login es huella/FaceID local del dispositivo; en un dispositivo nuevo se re-verifica la identidad con Didit.

## Índice

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Problema, alcance, resultados y métricas de éxito |
| [design.md](./design.md) | Modelo identidad-primero, estados, flujos, API, login y seguridad |
| [Fase 0](./phases/00-marco-modelo.md) | Marco de producto, modelo y decisiones cerradas |
| [Fase 1](./phases/01-identidad-compartida.md) | Servicio compartido Didit→Persona y workflow KYC único |
| [Fase 2](./phases/02-cuenta-identidad.md) | Alta = identidad (Persona + User sin efector ni contraseña) |
| [Fase 3](./phases/03-efector-propio-y-pago.md) | Crear clínica/consultorio propio + pago (desacoplado de la identidad) |
| [Fase 4](./phases/04-pantalla-seleccion-efector.md) | Pantalla post-login de selección de efector con mensaje + botón |
| [Fase 5](./phases/05-agregar-profesional-push.md) | El AdminEfector agrega al profesional + push |
| [Fase 6](./phases/06-ministerio-asistido.md) | Ministerio asistido por admin + token link (sin pago) |
| [Fase 7](./phases/07-desarmado-web-hardening.md) | Desarmar alta web + hardening (E.164, geo AR/UY, rate limit) |
| [Fase 8](./phases/08-cierre.md) | QA, documentación estable y borrado del plan |

## Dependencias actuales

- `DiditClient` + `RegisterPerson` (Didit→Persona, hoy para paciente/médico).
- `SignUpInstitutionalEfector` y `RequestMinistrySignup`.
- `StaffAccountInvitationService` (token de invitación, usuario sin contraseña).
- `SesionOperativaController` + wizard post-login (web) y `config_wizard_screen` (app).
- `PushNotificationSender` / `FcmHttpSender` (push FCM).
- Apps Flutter `paciente` y `personalsalud` (paquete `didit_sdk` ya presente).

## Orden de ejecución

Las fases 0 y 1 son bloqueantes: no se toca el alta actual hasta cerrar el contrato de identidad compartida. 2 precede a 3. Las fases 4, 5 y 6 pueden avanzar en paralelo una vez terminada la 2. La 7 (desarmado web) va al final, cuando la app ya cubre el alta. La 8 cierra.

## Estado de implementación

- Fase 0: decisiones cerradas en la conversación de diseño (sin contraseña, login biométrico local, Didit KYC compartido, consultorio crear-y-pagar, ministerio asistido, unión por invitación del AdminEfector, geo AR/UY, REFEPS/SISA en consultorio).
- Fase 1: hecho. Parámetro de workflow KYC compartido (`didit_kyc_workflow_id`, con fallback) y extracción del paso Didit→Persona a `DiditIdentityService` (consumido por `RegisterPerson` sin cambio de comportamiento).
- Fase 2: en curso. Implementado: `CreateIdentityAccount` (Persona + User passwordless desde Didit, sin efector/plan/pago) + endpoint público `POST /licencia/crear-cuenta` + ruta. Pendiente: login biométrico/re-verificación para esta cuenta.
- Fase 3: en curso. `registrar-efector` extendido: con `verification_id` resuelve la identidad por Didit (`resolveIdentity` → `CreateIdentityAccount`) y crea efector + plan + pago; sin `verification_id` mantiene el camino legacy. Queda para más adelante: ubicación con mapa y estimación server-side.
- Fase 4: en curso (web). `sesion-operativa/establecer` devuelve `puede_crear_efector`; el estado vacío de la web muestra mensaje + botón "Crear mi consultorio" (URL desde `institucionalSignupUrl`). Pendiente: mismo estado vacío en la app staff (`config_wizard_screen`).
- Fase 5: en curso (núcleo). `AddProfessionalToEfector` (asignación idempotente vía `EnsurePesAssignment` + push FCM vía `PushNotificationSender`). Pendiente: invocarlo desde la acción de admin que agrega profesionales y validar el payload de push en la app.
- Fase 6: en curso (núcleo). `CompleteMinistrySignupIdentity` (KYC → Persona + User passwordless + vínculo a la solicitud pendiente; la aprobación y el rol AdminMinisterio siguen en `RequestMinistrySignup::approve`). Pendiente: token de invitación de un solo uso + endpoint y flujo en la app.
- Fase 7: en curso. Web desarmada (`alta.html` → descarga/contacto, `signup.js` y estilos `.signup-*` eliminados, navegación/CTAs actualizados). Hardening: teléfono E.164 opcional (`ContactPhoneValidator`) y geo-gating AR/UY (`signupAllowedIso2`). Pendiente: rate limit/honeypot en endpoints públicos de alta.

## Cierre del plan

Al terminar la construcción:

1. Consolidar la narrativa vigente en `producto/alta-cuenta-licencia.md` y `producto/registro-paciente.md` si corresponde.
2. Registrar decisiones estables en `decisions/onboarding-comercial-self-service.md` (o una nueva).
3. Eliminar esta carpeta temporal.
