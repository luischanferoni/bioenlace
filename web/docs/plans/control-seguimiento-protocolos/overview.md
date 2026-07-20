# Overview — Control/Seguimiento + protocolos

## Objetivo

Unificar la entrada paciente de **control, seguimiento y solicitudes sobre tratamiento** dentro de **Solicitar Atención** (`atencion.necesito-atencion`), motivo **Control/Seguimiento**, y preparar la capa de **protocolos de cuidado** (plantillas por condición / perfil) alineada a FHIR **PlanDefinition** → instancia **CarePlan**.

Al terminar:

1. El atajo/intent `atencion.consultas-seguimiento-flow` **deja de existir** como entrada propia.
2. Control/Seguimiento muestra **tratamientos activos** (mismas acciones que hoy en el detalle del plan) y **diagnósticos activos/crónicos** con acciones derivadas de protocolos.
3. Existe un catálogo definitional mínimo (metadata) para protocolos preventivos/crónicos aplicables por reglas (edad, sexo, Condition, etc.).

## Problema

Hoy hay dos puertas:

| Puerta | Rol |
|--------|-----|
| Solicitar Atención | Malestar / Control/Seguimiento (turno) / Urgencia |
| Consultas y seguimiento | Renovación, ajuste, evolución, consulta por mensaje, turno de control |

Eso fragmenta UX y duplica caminos hacia el mismo CarePlan. Además no hay modelo para “te corresponde control X por edad/sexo/diagnóstico” más allá del CarePlan ya abierto o de CarePacks IA (otro dominio).

## Actores

| Actor | Uso |
|-------|-----|
| **Paciente** (app) | Elige Control/Seguimiento → elige ancla (tratamiento o condición/protocolo) → acción (turno, async, medicación, …) |
| **Staff** | Sin cambio de bandeja async / turnos en esta fase; consume lo que el paciente genera |
| **Desarrollo clínico** | Mantiene catálogo de protocolos (YAML primero; BD después si hace falta) |

## Fuera de alcance (este programa)

- Reemplazar o redefinir **CarePack / CareCohort** (packs IA pre/post consulta).
- Implementar recurso FHIR completo **Immunization** / calendario SISA como HIS local (se puede *referenciar* vacunas como acción de protocolo).
- Export FHIR de PlanDefinition hacia terceros.
- Staff authoring UI de protocolos (solo metadata/código en v1).
- Mezclar malestar nuevo o urgencia dentro del hub de control.

## Fases

| Fase | Entrega |
|------|---------|
| 0 | Marco: denominación, mapa FHIR, límites CarePack |
| 1 | Absorber consultas-seguimiento bajo Control/Seguimiento |
| 2 | Hub paciente: lista tratamientos + condiciones con acciones |
| 3 | Catálogo definitional de protocolos (PlanDefinition-lite) |
| 4 | Reglas de perfil (edad/sexo/…); primeros protocolos preventivos |
| 5 | Docs producto + retiro del plan |

## Criterio de éxito

- Paciente con CarePlan activo llega a renovar/ajustar/evolución/turno **sin** abrir el intent viejo.
- Paciente con Condition crónica ve al menos una acción útil (turno o consulta) desde el hub.
- Al menos **un** protocolo preventivo de ejemplo (p. ej. control por sexo/edad o placeholder vacunas) resoluble desde metadata, sin hardcode en orquestadores.
