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
- [ ] Conectar un cliente REFEPS con cobertura adecuada y auditar los intentos sin guardar datos innecesarios.
- [ ] Implementar revisión manual de casos no encontrados o ambiguos.

## Criterio de salida

Ningún usuario obtiene rol clínico por alta de identidad o sesión sin asignación previa. La verificación exige una coincidencia exacta de documento y bloquea los casos no encontrados, no disponibles o ambiguos.

## Bloqueo de integración pendiente

El repositorio solo referencia el método `getProfesionalesDeSantiago`; no incluye la implementación del cliente SISA/REFEPS ni configura el componente en el API frontend. La consulta tiene alcance Santiago, no nacional. El verificador falla cerrado si el componente no está disponible. No habilitar asignaciones clínicas en producción hasta conectar un proveedor con cobertura adecuada y acordar el circuito de revisión manual.
