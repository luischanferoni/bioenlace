# Fase 00 — Decisiones y modelo de producto

## Objetivo

Traducir las decisiones confirmadas a límites de dominio, estados y recorridos antes de sustituir los flujos existentes.

## Confirmado

- [x] Tres opciones: cuenta profesional personal sin pago; consultorio propio pago; clínica/centro pago.
- [x] Las tres altas empiezan en la app y verifican identidad con Didit (documento y selfie); no se reemplaza por un formulario manual de datos personales.
- [x] La cuenta profesional puede preceder a la asignación; queda sin acceso operativo.
- [x] AdminEfector busca personas existentes; solo puede asignar como personal Personas de origen `MOBILE_APP`.
- [x] Consultorio propio asigna AdminEfector automáticamente; el titular elige especialidad si también quiere la asignación de Médico.
- [x] El alta pública usa el código SISA oficial del catálogo local y rechaza efectores ya afiliados a un ministerio o con otra cuenta POOL.
- [x] Cancelar detiene renovaciones futuras, mantiene acceso hasta el fin del período pagado y suspende la licencia vencida si no se renueva.
- [x] REFEPS permanece mock por ahora; se contempla el estado pendiente y su revisión sin tratar un mock como verificación real.
- [x] Mercado Pago es el proveedor inicial en Argentina y la moneda es ARS; checkout mensual con conversión MEP vendedor, IVA 21% y revisión trimestral.
- [x] Frontend ofrece ingreso con la app. Admin se reserva para superadmin y conserva usuario/contraseña.
- [x] AdminEfector puede autenticarse en Personal de Salud aunque la app no exponga operaciones administrativas para ese rol.
- [x] Retirar del cliente y del servidor los recorridos legacy no usados; mantener separados Didit móvil y alta de pacientes con lector físico de DNI.
- [x] Guardar altas e intentos de pago; mostrar condiciones comerciales antes de autorizar.

## Pendientes de definición antes de habilitar los recorridos

- Determinar cómo clasificar altas asistidas por personal con Didit, si se conservan; nunca deben quedar marcadas como `MOBILE_APP`.
- Definir migración segura de Personas existentes a `LEGACY_UNKNOWN`, sin inferir procedencia por `id_user` o `acredita_identidad`.
- Verificar y documentar la actualización periódica del catálogo local desde el archivo oficial SISA.
- Definir reglas ante reembolsos y contracargos del producto Mercado Pago.

## Criterio de salida

Los límites quedan reflejados en autorización server-side, contratos API, modelo de estados y textos de producto. Las definiciones pendientes anteriores bloquean únicamente los recorridos afectados, no la cuenta profesional gratuita.
