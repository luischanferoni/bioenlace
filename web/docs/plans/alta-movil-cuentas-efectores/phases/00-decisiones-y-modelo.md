# Fase 00 — Decisiones y modelo de producto

## Objetivo

Traducir las decisiones confirmadas a límites de dominio, estados y recorridos antes de sustituir los flujos existentes.

## Confirmado

- [x] Tres opciones: cuenta profesional personal sin pago; consultorio propio pago; clínica/centro pago.
- [x] Las tres altas empiezan en la app y verifican identidad con Didit (documento y selfie); no se reemplaza por un formulario manual de datos personales.
- [x] La cuenta profesional puede preceder a la asignación; queda sin acceso operativo.
- [x] AdminEfector busca personas existentes; solo puede asignar como personal Personas de origen `MOBILE_APP`.
- [x] Consultorio propio asigna AdminEfector automáticamente; el titular elige especialidad si también quiere la asignación de Médico.
- [x] Clínica/centro público y privado comparten recorrido; antes de crear uno público se comprueba que no pertenezca ya a un ministerio.
- [x] REFEPS permanece mock por ahora; se contempla el estado pendiente y su revisión sin tratar un mock como verificación real.
- [x] Mercado Pago es el proveedor inicial en Argentina y la moneda es ARS; el checkout real no está habilitado.
- [x] Frontend ofrece ingreso con la app. Admin se reserva para superadmin y conserva usuario/contraseña.
- [x] Retirar del cliente y del servidor los recorridos legacy no usados; mantener separados Didit móvil y alta de pacientes con lector físico de DNI.
- [x] Guardar altas e intentos de pago; mostrar condiciones comerciales antes de autorizar.

## Pendientes de definición antes de habilitar los recorridos

- Determinar cómo clasificar altas asistidas por personal con Didit, si se conservan; nunca deben quedar marcadas como `MOBILE_APP`.
- Definir migración segura de Personas existentes a `LEGACY_UNKNOWN`, sin inferir procedencia por `id_user` o `acredita_identidad`.
- Resolver cómo accede a frontend un AdminEfector que es web-only mientras el frontend solo ofrece aprobación con la app y la política móvil excluye AdminEfector.
- Definir la clave autoritativa para detectar si un efector público ya pertenece a un ministerio.
- Definir impuestos, renovación y cancelación del producto Mercado Pago.

## Criterio de salida

Los límites quedan reflejados en autorización server-side, contratos API, modelo de estados y textos de producto. Las definiciones pendientes anteriores bloquean únicamente los recorridos afectados, no la cuenta profesional gratuita.
