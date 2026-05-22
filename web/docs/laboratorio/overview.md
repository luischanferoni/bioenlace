# Overview — Laboratorio

## Qué es

Integración con **laboratorios externos** que publican resultados en FHIR (`DiagnosticReport`, `Observation`). Bioenlace persiste una copia normalizada y la expone por API v1.

## Objetivo

- Paciente y profesional consultan resultados en contexto clínico (preferentemente `encounter`).
- Varios proveedores (p. ej. Sianlabs) con credenciales en configuración global.
- Solo sincronización **pull** (API o consola).

## Actores

- Paciente: listar y sincronizar sus resultados.
- Profesional: ver resultados del encounter con acceso clínico.
- Operaciones: `php yii laboratory-sync/persona`.

## Fuera de alcance

- Alta manual de resultados en Yii (`LaboratorioController` — retirado).
- Catálogo NBU / equivalencias SNOMED locales (retirado).
- Push desde el LIS.
