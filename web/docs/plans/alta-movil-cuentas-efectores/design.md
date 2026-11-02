# Design — Alta móvil de cuentas y efectores

## Decisiones cerradas

| Tema | Decisión |
|------|----------|
| Canal de alta | Las tres opciones se crean desde Personal de Salud |
| Verificación civil | Documento + selfie desde la app; sus atributos verificados se reutilizan |
| Procedencia de Persona | Registrar el origen en servidor: `MOBILE_APP`, `STAFF_DNI_READER` o `LEGACY_UNKNOWN`; solo `MOBILE_APP` permite asignar personal |
| E-mail | No requerido ni verificado para crear una cuenta; nunca es autenticación ni recuperación suficiente |
| Cuenta profesional | Puede existir sin centro; no tiene acceso clínico hasta que AdminEfector la incorpore |
| Incorporación a centro | Solo la inicia AdminEfector buscando una persona existente; no existe “sumarme a un centro” self-service |
| REFEPS | Mock por ahora. No aplica a identidad ni a crear/administrar centro; se evalúa al asignar rol clínico y antes de comprar consultorio propio |
| Consultorio propio | Alta paga; se asigna AdminEfector automáticamente. Elegir una especialidad permite solicitar la asignación adicional como Médico |
| Clínica/centro | Alta paga; asigna AdminEfector, cuyo acceso web debe resolverse antes de habilitar el flujo |
| Pago | Mercado Pago para Argentina, en ARS. Mostrar precio, impuestos, renovación y cancelación antes de autorizar; activar licencia solo tras confirmación real |
| Persistencia | Guardar el alta y cada intento de pago con idempotencia para reanudar fallos sin duplicados |
| Web | Frontend: ingreso con la app. Admin: usuario/contraseña solo para superadmin |
| Recuperación | App: nueva verificación de identidad. Web-only: recuperación asistida con verificación de identidad, revocación de credenciales y sesiones |
| Acceso móvil | Allowlist explícita de roles aplicada en servidor; AdminEfector excluido por ahora; Administrativo y futuros roles web-only por defecto |
| Legacy | Retirar del servidor y los clientes los flujos no usados de registro/login/activación; conservar registros de pacientes con Didit y alta asistida por DNI físico |

## Estados de identidad y habilitación

La identidad verificada no implica matrícula, asignación, licencia ni acceso.

1. `identity_verified`: Persona identificada y cuenta creada.
2. `professional_verification_pending`: REFEPS pendiente o requiere revisión manual.
3. `professional_verified`: habilitación compatible confirmada.
4. `assigned`: AdminEfector creó la asignación al efector.
5. `license_pending_payment` / `license_active`: checkout pendiente o pago confirmado.

Solo una asignación activa con rol clínico válido y licencia habilitada permite operar. Fallo de REFEPS y falta de respuesta se distinguen; un error temporal no equivale a rechazo.

## Procedencia de Persona y asignación

- `personas.id_user` indica una vinculación con una cuenta, no el canal ni el método con que se creó la Persona. La ruta de alta asistida por lector DNI también puede crear/vincular un User.
- `acredita_identidad` indica acreditación, no procedencia. Los datos/IDs de Didit por sí solos tampoco sustituyen un campo de origen explícito.
- Agregar a `personas` un campo de procedencia (nombre propuesto: `origen_creacion`) con valores controlados; escribirlo únicamente desde el backend según el flujo efectivamente completado y nunca aceptar el valor como confiable desde el cliente.
- `MOBILE_APP` se asigna al completar en Personal de Salud una verificación Didit aprobada y crear/recuperar la cuenta.
- `STAFF_DNI_READER` identifica altas asistidas a partir de lectura física de DNI. El flujo asistido de personal que usa Didit, si se conserva, debe tener una clasificación no móvil explícita y nunca marcarse `MOBILE_APP`.
- Personas existentes sin evidencia fiable se clasifican `LEGACY_UNKNOWN`. La migración no infiere el origen a partir de `id_user`, `acredita_identidad` o de la mera presencia de referencias Didit.
- La búsqueda para asignar personal puede limitar sus candidatos a `MOBILE_APP`, pero la autorización decisiva se verifica también en el caso de uso de asignación. Búsquedas generales (admisión, pacientes y otros dominios) no deben heredar este filtro.
- Las excepciones de provisioning confiable del sistema se identifican y prueban por separado; no deben convertirse en un bypass para asignaciones de usuarios.

## Seguridad de checkout

- Nunca enviar ni persistir PAN/CVV en Bioenlace; usar checkout alojado/tokenización del proveedor.
- El servidor calcula precios y condiciones desde metadata, no acepta total ni rol de cliente como autoridad.
- Un intento tiene clave idempotente y estados monotónicos; webhooks se autentican y deduplican.
- Un pago aprobado se concilia antes de crear/activar entitlement; los reintentos no vuelven a cobrar.
- Los fallos dejan el alta recuperable y sin permisos pagos.

## QR de acceso web

El QR de login es distinto del QR para asociar un dispositivo al alta. Autoriza un desafío específico del navegador, de un solo uso, corto, con origin/audience fijados y confirmación explícita desde la app. No expone tokens de sesión en el QR. Se registra actor, dispositivo, sesión y resultado.

## Acceso web y transición

Frontend autentica mediante aprobación desde la app; no muestra contraseña ni passkey. Admin conserva usuario/contraseña para superadmin exclusivamente. No se habilita el alta comercial de clínicas hasta resolver cómo accede a frontend un AdminEfector, hoy excluido de la política móvil. El retiro de endpoints legacy no elimina el registro vigente de pacientes ni la excepción de alta asistida con lector físico de DNI.

## Decisiones técnicas por cerrar antes del checkout

- Modelo de renovación, impuestos/facturación y cancelación/prorrateo de Mercado Pago.
- En el flujo con lector, decidir cómo clasificar otras altas asistidas no móviles (por ejemplo, Didit ejecutado por personal) sin etiquetarlas falsamente como `STAFF_DNI_READER` ni habilitarlas para asignación.
- Protocolo de revisión manual de REFEPS y condiciones para sustituir el mock por la consulta real.
- Atributos mínimos que se conservan de la verificación y retención/auditoría.
- Identificador confiable para detectar un efector público ya incorporado a una cuenta ministerial.
