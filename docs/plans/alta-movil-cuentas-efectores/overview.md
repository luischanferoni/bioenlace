# Overview — Alta móvil de cuentas y efectores

## Objetivo

Unificar creación de identidad, altas comerciales, incorporación profesional y acceso móvil/web. La verificación civil se realiza en Personal de Salud; las capacidades operativas dependen de roles, asignaciones activas y licencias.

## Recorridos acordados

| Recorrido | Identidad / REFEPS | Pago | Rol y canales |
|-----------|--------------------|------|---------------|
| Cuenta profesional personal | Verificación Didit desde la app; REFEPS al ser incorporado a un centro | No | Elige un centro activo (filtro público/privado y provincia) y se registra la solicitud; sin asignación no hay acceso operativo. AdminEfector la incorpora. Solo se puede asignar una Persona de origen `MOBILE_APP` |
| Consultorio propio | Identidad Didit desde la app + REFEPS (mock por ahora) antes del checkout | Sí | AdminEfector automático; si el titular elige una especialidad, se incorpora además como Médico según la política REFEPS |
| Clínica o centro | Identidad Didit desde la app; no requiere REFEPS para administrar | Sí | AdminEfector puede autenticarse en la app aunque no tenga operaciones visibles; REFEPS solo si también se incorpora como profesional |
| Personal incorporado por un centro | El AdminEfector busca una Persona existente; solo se asignan Personas de origen `MOBILE_APP`; REFEPS antes del rol clínico (mock por ahora) | Lo cubre la licencia del centro | Canales determinados por cada rol, no por el hecho de tener cuenta |
| AdminMinisterio | Flujo asistido, fuera del self-service comercial | Según acuerdo | Web, salvo decisión futura explícita |
| Administrativo y roles futuros | Cuenta e invitación/asignación según política | Licencia del centro cuando corresponda | Web por defecto; app solo tras habilitación explícita |

## Límite de una cuenta profesional sin centro

Antes de la verificación Didit, la app lista los centros activos en Bioenlace y permite filtrarlos por sector (público/privado) y provincia. La selección es obligatoria y se persiste como solicitud de incorporación; todavía no asigna roles ni habilita acceso. El AdminEfector inicia la vinculación efectiva desde la búsqueda de personas. La app muestra un estado informativo hasta que exista una asignación que habilite una sesión.

## Alcance del programa

- Alta de identidad desde móvil sin exigir ni verificar e-mail.
- Estado profesional REFEPS separado de identidad civil y rol RBAC.
- Selector de las tres opciones móviles: cuenta profesional, consultorio propio y clínica/centro.
- Persistencia de solicitudes/intentos para reanudar fallos sin duplicar identidad, efector, licencia o cobro.
- Checkout transparente para consultorio y clínica/centro.
- Frontend con ingreso confirmado desde la app; panel Admin con usuario/contraseña solo para superadmin.
- AdminEfector puede iniciar sesión en Personal de Salud; la app no le habilita operaciones administrativas/clínicas por ese solo hecho.
- Recuperación de acceso alineada con los canales vigentes; no depender de passkeys como único método para un rol que no las ofrece.
- Restricción de acceso a Personal de Salud por rol en el servidor.
- Procedencia de creación de Persona explícita y confiable: `MOBILE_APP`, `STAFF_DNI_READER` y `LEGACY_UNKNOWN`; solo `MOBILE_APP` habilita asignaciones de personal.
- Preservar el alta asistida de pacientes con lectura física de DNI, sin confundirla con creación de cuenta en la app ni habilitar por ella una asignación de personal.
- Retiro de llamadas y rutas legacy no usadas tanto del servidor como de los clientes móviles/web.
- Retiro de flujos legacy, conservando identidades, asignaciones, contratos y licencias existentes.

## Fuera de alcance

- Aprobación automática o asignación de rol a partir de la solicitud de incorporación.
- REFEPS como requisito para que AdminEfector cree o administre un centro.
- Usar `id_user`, `acredita_identidad` o campos Didit como sustituto implícito de la procedencia de alta.
- Habilitar Administrativos en la app sin una decisión explícita.
- Recuperación mediante e-mail como único factor.
- Alta comercial sujeta a catálogo SISA vigente, configuración y QA de Mercado Pago y reglas pendientes de reembolso/contracargo.
