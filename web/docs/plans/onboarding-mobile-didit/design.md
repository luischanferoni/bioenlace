# Design — Alta de cuenta móvil con identidad Didit

## Separación de responsabilidades

| Pieza | Responsabilidad |
|-------|-----------------|
| Didit KYC | Verificar que la persona es quien dice ser (documento + selfie + liveness) |
| Persona | Entidad raíz verificada (`acredita_identidad=1`, `didit_reference_id`) |
| User | Credencial de acceso sin contraseña (`password_set_at = null`) |
| Rol | Capa: `AdminEfector`, `AdminMinisterio`, profesional, paciente |
| Efector + BillingAccount | Organización y cuenta de facturación (pool o propia) |
| Pago | Activa los entitlements del efector propio |
| Sesión operativa | Contexto efector/servicio/área para operar |

## Modelo identidad-primero

```
Didit KYC (workflow único compartido)
        │
        ▼
Persona + User  (cuenta de identidad; sin efector, sin contraseña, sin pago)
        │
   "Seleccionar efector" (post-login)
        │
   ┌────┴──────────────────────────────┐
   │ 1. Un AdminEfector te agrega       │ 2. Creás tu propio efector
   │    (pool; push → aparece)          │    (formulario + pago → activo)
   └───────────────────────────────────┘
```

La creación de cuenta **nunca** exige efector ni pago. El efector es una capa posterior.

## Estados

- **Cuenta de identidad**: Persona + User verificados, sin efector. Puede ver la pantalla de selección de efector.
- **Afiliado (pool)**: el profesional fue agregado a un efector por su AdminEfector. Opera bajo la cuenta de esa organización; no paga.
- **Efector propio activo**: el usuario creó su clínica/consultorio y pagó. Entitlements activos.
- **Cobertura ministerial (público)**: efector público asociado a un ministerio; `PENDING` hasta aprobación; no paga.
- **Ministerio**: `BillingAccount` tipo ministerio (pagador/pool); alta asistida; no paga.

## Flujos por perfil

### Clínica/centro (privado)

App → KYC → identidad → "crear mi clínica/centro" (nombre, ubicación, plan AMB/EMER/IMP, pago) → efector activo.

### Clínica/centro (público)

Idem, pero en vez de pago: elegir ministerio de `catalogo-ministerios` + solicitar cobertura → `PENDING` (aprobación), sin pago.

### Consultorio (privado)

App → KYC → identidad → "crear mi consultorio" (REFEPS/SISA + nombre + ubicación + plan ambulatorio + pago) → efector activo.

### Profesional de una organización

1. Crea su cuenta de identidad en la app (KYC) y queda sin efector.
2. Se comunica con el AdminEfector de su organización.
3. El AdminEfector lo agrega como profesional.
4. Push a la app → el efector aparece en la lista → opera.

### Ministerio (asistido)

1. Admin (backoffice) crea la solicitud (organización + persona de contacto) → `PENDING`.
2. Se genera un token de invitación (reusar `StaffAccountInvitationService`) y se envía el link.
3. La persona abre el link en la app → KYC → Persona + User + rol `AdminMinisterio` → vinculada a la cuenta del ministerio. Sin pago.

## Login

- **Día a día**: huella/FaceID local del dispositivo.
- **Dispositivo nuevo/reinstalación**: re-verificación Didit → resolución de Persona por `didit_reference_id` o documento → sesión nueva → enrolamiento de huella local. Base actual: `AuthController::actionLoginBiometrico`.

## API

| Endpoint | Estado | Notas |
|----------|--------|-------|
| `POST /registro/registrar` | extender | `RegisterPerson` como único paso de identidad; crear también User sin contraseña para staff |
| `GET /registro/config-movil` | extender | exponer workflow KYC compartido (`didit_kyc_workflow_id`) |
| `POST /licencia/registrar-efector` | extender | acepta `verification_id` (Didit) y crea efector + plan + pago sobre la identidad verificada |
| `GET /licencia/catalogo-ministerios` | mantener | sector público |
| `POST /licencia/solicitar-ministerio` | quitar | reemplazado por alta asistida |
| `POST /sesion-operativa/establecer` | extender | flags `puede_crear_efector`, `invitacion_pendiente`, `cuenta_pendiente_pago` |
| Admin: agregar profesional + push | nuevo/extender | vincula Persona al efector y notifica |

## Seguridad y privacidad

- La identidad se verifica con Didit antes de crear cualquier efector o rol.
- Sin contraseñas: se elimina la superficie de ataque por contraseñas débiles.
- Geo-gating AR/UY en servidor (no confiar solo en el cliente).
- Teléfono E.164 opcional; no se usa como factor salvo decisión posterior.
- Rate limit + honeypot en endpoints públicos de alta.
- Push de "te agregaron" solo al usuario afectado, sin exponer datos de terceros.

## Alternativas descartadas

### Mantener el alta web + Didit hosted session

Descartada por decisión de producto: la app Personal de Salud es la puerta de entrada; el SDK nativo de Didit da mejor UX que la redirección hosted.

### Contraseña como respaldo

Descartada: login biométrico local únicamente; re-verificación Didit como recuperación en dispositivo nuevo.

### Pago atómico con la creación de cuenta

Descartado: la cuenta es identidad y no debe quedar condicionada al pago. El pago solo existe en "crear mi propio efector".

### Workflow KYC separado para staff

Descartado: el KYC de Didit es agnóstico de rol; se reutiliza el mismo workflow.
