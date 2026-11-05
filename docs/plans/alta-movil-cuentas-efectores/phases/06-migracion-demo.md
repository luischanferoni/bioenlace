# Migración de cuentas — entorno de demo

## Contexto

El entorno puede contener pocos usuarios reales, pero esto no elimina la necesidad de preservar las relaciones clínicas y comerciales ni justifica inferir la procedencia de una Persona. Aplicar una migración aditiva y reversible.

## Estrategia

- Preservar `user`, `personas`, PES, efectores, licencias, cuentas de facturación y sesiones existentes.
- Agregar el campo de procedencia de Persona con valor seguro `LEGACY_UNKNOWN` para los registros existentes; no inferir `MOBILE_APP` a partir de `id_user`, `acredita_identidad` o campos Didit aislados.
- Marcar nuevos registros en backend de acuerdo con su flujo verificado. Solo `MOBILE_APP` permite nuevas asignaciones de personal.
- Mantener los JWT en almacenamiento seguro y la revocación server-side ya implementados.
- No solicitar enrolamiento de passkey como prerequisito del corte: frontend usa aprobación con la app y Admin conserva usuario/contraseña solo para superadmin.
- Retirar llamadas legacy de clientes y sus rutas/actions obsoletas sin borrar el registro Didit ni el alta asistida de pacientes con lector físico.
- Probar en una copia de demo: migración, rollback de esquema, retención de vínculos User/Persona/PES y rechazo de asignaciones para `LEGACY_UNKNOWN` y `STAFF_DNI_READER`.

## Criterio de salida

La migración no cambia identidad ni asignaciones existentes; la procedencia histórica queda desconocida de forma conservadora. La autenticación y la elegibilidad de asignación siguen las políticas vigentes del plan.
