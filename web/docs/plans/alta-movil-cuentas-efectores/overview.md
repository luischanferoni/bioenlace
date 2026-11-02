# Overview — Alta móvil de cuentas y efectores

## Objetivo

Unificar creación de identidad, altas comerciales, incorporación profesional y acceso móvil/web. La verificación civil se realiza en Personal de Salud; las capacidades operativas dependen de roles, asignaciones activas y licencias.

## Recorridos acordados

| Recorrido | Identidad / REFEPS | Pago | Rol y canales |
|-----------|--------------------|------|---------------|
| Cuenta profesional personal | Verificación Didit desde la app; REFEPS al ser incorporado a un centro | No | Sin asignación, sin acceso operativo; AdminEfector lo agrega. Solo se puede asignar una Persona de origen `MOBILE_APP` |
| Consultorio propio | Identidad Didit desde la app + REFEPS (mock por ahora) antes del checkout | Sí | AdminEfector automático; si el titular elige una especialidad, se incorpora además como Médico según la política REFEPS |
| Clínica o centro | Identidad; no requiere REFEPS para administrar | Sí | AdminEfector web-only por ahora; REFEPS solo si también se incorpora como profesional |
| Personal incorporado por un centro | El AdminEfector busca una Persona existente; solo se asignan Personas de origen `MOBILE_APP`; REFEPS antes del rol clínico (mock por ahora) | Lo cubre la licencia del centro | Canales determinados por cada rol, no por el hecho de tener cuenta |
| AdminMinisterio | Flujo asistido, fuera del self-service comercial | Según acuerdo | Web, salvo decisión futura explícita |
| Administrativo y roles futuros | Cuenta e invitación/asignación según política | Licencia del centro cuando corresponda | Web por defecto; app solo tras habilitación explícita |

## Límite de una cuenta profesional sin centro

Crear identidad no crea una asignación PES, no permite buscar instituciones para sumarse y no concede acceso a datos clínicos. El AdminEfector inicia la vinculación desde la búsqueda de personas. La app muestra un estado informativo hasta que exista una asignación que habilite una sesión.

## Alcance del programa

- Alta de identidad desde móvil sin exigir ni verificar e-mail.
- Estado profesional REFEPS separado de identidad civil y rol RBAC.
- Selector de las tres opciones móviles: cuenta profesional, consultorio propio y clínica/centro.
- Persistencia de solicitudes/intentos para reanudar fallos sin duplicar identidad, efector, licencia o cobro.
- Checkout transparente para consultorio y clínica/centro.
- Frontend con ingreso confirmado desde la app; panel Admin con usuario/contraseña solo para superadmin.
- Recuperación de acceso alineada con los canales vigentes; no depender de passkeys como único método para un rol que no las ofrece.
- Restricción de acceso a Personal de Salud por rol en el servidor.
- Procedencia de creación de Persona explícita y confiable: `MOBILE_APP`, `STAFF_DNI_READER` y `LEGACY_UNKNOWN`; solo `MOBILE_APP` habilita asignaciones de personal.
- Preservar el alta asistida de pacientes con lectura física de DNI, sin confundirla con creación de cuenta en la app ni habilitar por ella una asignación de personal.
- Retiro de llamadas y rutas legacy no usadas tanto del servidor como de los clientes móviles/web.
- Retiro de flujos legacy, conservando identidades, asignaciones, contratos y licencias existentes.

## Fuera de alcance

- Self-service para que un profesional elija o solicite sumarse a un centro.
- REFEPS como requisito para que AdminEfector cree o administre un centro.
- Usar `id_user`, `acredita_identidad` o campos Didit como sustituto implícito de la procedencia de alta.
- Habilitar Administrativos o AdminEfector en la app sin una decisión explícita.
- Recuperación mediante e-mail como único factor.
- Alta comercial antes de resolver el acceso web de AdminEfector, el control ministerial y las condiciones pendientes de Mercado Pago.
