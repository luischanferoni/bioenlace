# Overview — Captura clínica

## Qué es

Capacidad de ingresar **audio o texto libre** y convertirlo en datos estructurados persistidos (consulta multimodelo, entidad única vía asistente, o borrador de chat), con trazabilidad de lo no mapeable y post-procesado (corrección médica, resúmenes del historial).

## Objetivo

- Unificar criterios de **nivel de carga** (multimodelo vs. destino único).
- No perder información útil que no encaja en el contrato del flujo (`unmapped`).
- Mejorar calidad del texto antes de IA clínica (diccionarios + IA condicional).
- Ofrecer al staff un **panorama del historial** sin exponer datos sensibles fuera de reglas SNOMED.

## Actores

- Médico / enfermería (consulta, evolución, quirófano redactar).
- Paciente (motivos, chat pre-consulta, turnos vía asistente).
- Administración (reglas de sensibilidad, regeneración de resúmenes).

## Alcance

| Incluido | Fuera de alcance |
|----------|------------------|
| Niveles 1 y 2, política unmapped | Detalle de contratos YAML del asistente (ver asistente/) |
| Pipeline corrección texto | Pricing detallado (ver costos/) |
| Plan resumen timeline + sensibilidad | Implementación UI Flutter nativa |

## Flujos detallados

Ver [README.md](./README.md).
