# Overview — Alta móvil de cuentas y efectores

## Objetivo

Unificar creación de identidad, altas comerciales, incorporación profesional y acceso en web/app sin contraseñas. La verificación civil se realiza en Personal de Salud; las capacidades operativas dependen de roles, asignaciones activas y licencias.

## Recorridos acordados

| Recorrido | Identidad / REFEPS | Pago | Rol y canales |
|-----------|--------------------|------|---------------|
| Cuenta profesional personal | Verificación de identidad al crear cuenta; REFEPS al ser incorporado a un centro | No | Sin asignación, sin acceso operativo; AdminEfector lo agrega. Web/app cuando se le asigne rol habilitado |
| Consultorio propio | Identidad + REFEPS antes del checkout | Sí | Administración del consultorio y rol clínico separado; web y app para el rol clínico |
| Clínica o centro | Identidad; no requiere REFEPS para administrar | Sí | AdminEfector web-only por ahora; REFEPS solo si también se incorpora como profesional |
| Personal incorporado por un centro | El AdminEfector lo busca y asigna; identidad de la persona debe existir; REFEPS antes del rol clínico | Lo cubre la licencia del centro | Canales determinados por cada rol, no por el hecho de tener cuenta |
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
- Autenticación web con passkey y, para roles con app habilitada, aprobación QR.
- Recuperación web-only asistida mediante verificación de identidad y revocación de credenciales/sesiones anteriores.
- Restricción de acceso a Personal de Salud por rol en el servidor.
- Retiro de flujos legacy, conservando identidades, asignaciones, contratos y licencias existentes.

## Fuera de alcance

- Self-service para que un profesional elija o solicite sumarse a un centro.
- REFEPS como requisito para que AdminEfector cree o administre un centro.
- Habilitar Administrativos o AdminEfector en la app sin una decisión explícita.
- Recuperación mediante e-mail como único factor.
- Integración de pago sin proveedor, moneda, impuestos y política de renovación definidos.
