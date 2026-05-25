# Overview — Recordatorios care plan

## Objetivo

Que el paciente reciba **recordatorios locales** (alarma en el teléfono) alineados a sus **care plans activos**, con posibilidad de **activar/desactivar** sin depender de push FCM por cada horario de medicación.

## Actores

- **Paciente** (app Flutter): activa recordatorios, recibe notificaciones locales, abre detalle del tratamiento al tocar.
- **Staff** (futuro cercano): al indicar medicación, puede cargar horarios estructurados en `medication_request.dosage_json`.
- **Sistema**: API expone agenda derivada; el dispositivo programa/cancela alarmas.

## Alcance por fase

| Fase | Entrega |
|------|---------|
| 1 | Contrato `dosage_json.timing`, builder de agenda, `GET recordatorios-como-paciente` |
| 2 | Módulo Flutter local, switch global en Configuración, sync al abrir app / refrescar tratamientos |
| 3 | Switches por plan y por ítem; horarios manuales si falta `timing` en API |
| 4 | `service-request`, preferencias en servidor, intent asistente |

## Fuera de alcance

- Push FCM por cada toma (queda turnos/alertas consultorio).
- Recordatorios de turnos (ya existe `turno_notificacion_programada`).
- Adherencia registrada (“marqué que tomé”) — programa aparte si se pide después.
- Parseo IA de `dosage_text` libre en v1.

## Dependencias

- Care plans activos: `GET /api/v1/clinical/care-plans/active` (existente).
- Actividades `medication-request` vía `care_plan_activity` (existente).
- App paciente: `CarePlanService`, `ConfiguracionScreen` (existente).

## Relación con otros programas

- **Receta electrónica**: documento emitido; no sustituye recordatorios de tratamiento crónico en care plan.
- **Pre-consulta / check-in**: descartado por ahora; sin solapamiento.
