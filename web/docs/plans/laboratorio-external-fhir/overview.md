# Overview — Laboratorio externo FHIR

## Qué es

Sincronización de **resultados de laboratorio** desde sistemas LIS externos que exponen **FHIR R4** (`DiagnosticReport`, `Observation`), persistidos en Bioenlace y expuestos por API v1.

## Objetivo

- Un registro canónico por informe y analitos, enlazado a `personas` y preferentemente a `encounter`.
- Varios proveedores (Sianlabs primero) con credenciales en config global.
- Solo **pull** (job o endpoint de sincronización).

## Actores

- Paciente: ver y disparar sync de sus resultados.
- Profesional: ver resultados del encounter.
- Sistema: job/consola de sincronización programada (futuro).

## Fuera de alcance

- LIS productivo interno (`LaboratorioController`, import CSV dengue, tablas `laboratorio*`).
- Equivalencias NBU → SNOMED (opción A: códigos del FHIR directo).
- Push / webhooks del LIS.
