# Producto — Visión general

## Qué es

Documentación de **producto digital** orientada al paciente y al profesional: cómo se registran, qué pueden hacer en app/SPA antes y durante la atención, y qué capacidades transversales (chat, medios, videollamada) se apoyan en el asistente y la API.

## Objetivo

- Unificar la narrativa de **apps móvil** (paciente, médico) con los endpoints que ya existen en API v1.
- Separar **registro de identidad** (Verifik, MPI, REFEPS) del **registro administrativo manual** en web.

## Actores

| Actor | Canal principal |
|-------|-----------------|
| Paciente | App paciente, SPA paciente |
| Médico | App médico, SPA profesional |
| Administración | Web Yii (alta manual de personas) |

## Alcance

Incluye: matriz de capacidades de experiencia, flujo `RegistroController` / `RegistroService`, integración signup móvil.

No incluye: detalle de turnos (dominio Turnos) ni contratos YAML del asistente.
