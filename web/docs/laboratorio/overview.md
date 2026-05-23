# Overview — Laboratorio

## Qué es

Integración con **laboratorios externos** que publican resultados en FHIR (`DiagnosticReport`, `Observation`). Bioenlace persiste una copia normalizada y la expone por API v1.

## Objetivo

- Paciente y profesional consultan resultados en contexto clínico (preferentemente `encounter`).
- Varios proveedores (p. ej. Sianlabs) con credenciales en configuración global.
- Solo sincronización **pull** (API o consola).

### Paciente (producto)

| Necesidad | Flow |
|-----------|------|
| Ver informes y analitos ya en Bioenlace | [consultar-resultados-paciente](./flows/consultar-resultados-paciente.md) |
| Asistente | [intents-laboratorio-paciente](./flows/intents-laboratorio-paciente.md) |

### Operaciones

| Necesidad | Flow |
|-----------|------|
| Ingesta pull LIS (cron / lote) | [ingesta-cron](./flows/ingesta-cron.md) |

## Actores

- Paciente: listar y ver detalle (`mis-resultados-como-paciente`, ui_json) — **solo lectura en BD**.
- Profesional: ver resultados del encounter (`por-encounter`) con acceso clínico.
- Operaciones / cron: `php yii laboratory-sync/lote` o `laboratory-sync/persona`.

## Fuera de alcance

- Alta manual de resultados en Yii (`LaboratorioController` — retirado).
- Catálogo NBU / equivalencias SNOMED locales (retirado).
- Push desde el LIS.
