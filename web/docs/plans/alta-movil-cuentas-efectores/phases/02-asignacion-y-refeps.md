# Fase 02 — Asignación por AdminEfector y REFEPS

## Objetivo

Mantener la búsqueda/invitación bajo control del AdminEfector y exigir habilitación profesional antes de asignar capacidades clínicas.

## Checklist

- [x] Resolver profesional por Persona existente; no crear cuentas duplicadas por invitación.
- [x] Al asignar rol clínico, exigir REFEPS confirmado y coincidencia exacta del DNI devuelto.
- [x] Diferenciar coincidencia, no encontrado, respuesta temporalmente indisponible y revisión manual.
- [x] Bloquear rol clínico hasta confirmación; ninguna respuesta negativa o ambigua crea la asignación.
- [x] No requerir REFEPS para asignación AdminEfector ni para administrar el centro.
- [x] Aplicar la misma regla cuando AdminEfector se incorpora a sí mismo a un servicio clínico.
- [x] Restringir la creación de asignaciones a AdminEfector del mismo centro o superadmin.
- [x] Impedir que el wizard cree asignaciones: solo puede establecer una sesión sobre una asignación preexistente.
- [x] Implementar el punto de conexión con el componente Yii `sisa`; si el componente o método no está disponible, falla cerrado.
- [ ] Confirmar que la clase SISA y sus credenciales están presentes en el entorno desplegado.
- [ ] Persistir una auditoría minimizada de los intentos REFEPS.
- [ ] Implementar revisión manual de casos no encontrados o ambiguos.

## Criterio de salida

Ningún usuario obtiene rol clínico por alta de identidad o sesión sin asignación previa. La verificación exige una coincidencia exacta de documento y bloquea los casos no encontrados, no disponibles o ambiguos.

## Alcance de integración

El API configura la misma referencia de componente SISA que la aplicación web e intenta invocar `getProfesionalesDeSantiago`. El código de la clase adaptadora no está incluido en este repositorio; sin ella, el verificador devuelve `unavailable` y no crea asignaciones. La respuesta vacía se limita al alcance informado por el proveedor; la revisión manual queda como mejora pendiente, no como bloqueo del resto del onboarding.
