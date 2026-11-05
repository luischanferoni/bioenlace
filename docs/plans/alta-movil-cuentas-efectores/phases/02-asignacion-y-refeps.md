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
- [ ] Registrar el origen de cada Persona en servidor: `MOBILE_APP`, `STAFF_DNI_READER` o `LEGACY_UNKNOWN`; toda alta de cuenta Didit desde la app se marca `MOBILE_APP`.
- [ ] Autorizar asignaciones de personal únicamente para origen `MOBILE_APP`, en la búsqueda específica de candidatos y en el caso de uso de asignación.
- [ ] Migrar Personas existentes sin evidencia inequívoca a `LEGACY_UNKNOWN`; no inferir origen a partir de `id_user`, `acredita_identidad` ni referencias Didit aisladas.
- [ ] Mantener sin filtro de origen las búsquedas generales para pacientes/admisión; limitar el filtro `MOBILE_APP` a la selección de personal.
- [x] Impedir que el wizard cree asignaciones: solo puede establecer una sesión sobre una asignación preexistente.
- [x] Implementar el punto de conexión con el componente Yii `sisa`; si el componente o método no está disponible, falla cerrado.
- [x] Mantener el mock REFEPS actual por ahora.
- [ ] Hacer explícito que el mock no representa una verificación real y que no puede habilitarse inadvertidamente en producción.
- [ ] Cubrir respuestas mock de confirmado, no encontrado e indisponible/revisión.
- [x] Configurar parámetro `sisa.mockMode` en `common/config/params.php` para alternar entre mockup e integración real.
- [x] Registrar componente en `frontend/config/main.php` con clase DDD correcta.
- [ ] Persistir una auditoría minimizada de los intentos REFEPS.
- [ ] Implementar aviso de estado pendiente y una pantalla/acción de revisión restringida a superadmin para casos no disponibles o ambiguos.
- [ ] Asegurar que un estado no encontrado, indisponible o ambiguo no cree ni habilite por sí mismo una asignación clínica.

## Criterio de salida

Ningún usuario obtiene rol clínico por alta de identidad o sesión sin asignación previa. La verificación exige una coincidencia exacta de documento y bloquea los casos no encontrados, no disponibles o ambiguos.

## Alcance de integración

El nombre actual `getProfesionalesDeSantiago` y el mock no demuestran el alcance del padrón. Al integrar el proveedor real, confirmar que el endpoint provincial consulta la base nacional y actualizar nombres/documentación según corresponda. La revisión manual de casos no disponibles debe permitir retomar el alta sin conceder una asignación clínica antes de la aprobación.
