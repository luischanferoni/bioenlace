# Programa clínico FHIR — Visión general

## Qué es

Programa de **rediseño del dominio clínico** hacia un modelo alineado a HL7 FHIR, con **CarePlan** como eje del tratamiento activo del paciente y **Encounter** sustituyendo el legado de consultas Yii.

## Objetivo

- Unificar persistencia y reglas detrás de **API v1**.
- Servir **Flutter**, SPA y asistente con los mismos endpoints.
- Retirar progresivamente MVC clínico legacy sin dual-write.

## Actores

| Actor | Interés |
|-------|---------|
| Equipo producto / clínico | Modelo de care plans, especialidades |
| Desarrollo API y clientes | Contratos estables, fases entregables |
| Operaciones | Migraciones BD, RBAC nuevas rutas |

## Alcance del programa

Ver [PROGRAM.md](./PROGRAM.md) y el tablero [MIGRATION_STATUS.md](./MIGRATION_STATUS.md). Las fases ejecutables están en [phases/](./phases/).

Fuera de alcance (por ahora): interoperabilidad export (bundles receta digital), perfiles regulatorios externos.

## Canal de producto

**API v1** (`frontend/modules/api/v1`) + clientes. Frontend Yii clásico de consultas: fase final u obsoleto ([phases/12-yii-web.md](./phases/12-yii-web.md)).
