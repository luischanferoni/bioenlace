# Fase 4 — API lectura y sync

## Objetivo

Endpoints paciente y por encounter; RBAC.

## Entregables

- [x] `LaboratoryResultController`
- [x] Rutas en `main.php`
- [x] Migración RBAC `m260523_100002_api_laboratory_rbac`
- [x] Consola `LaboratorySyncController` (sync por persona)

## Rutas

| Método | Ruta |
|--------|------|
| GET | `/api/v1/clinical/laboratory-results/mis-resultados` |
| POST | `/api/v1/clinical/laboratory-results/sincronizar` |
| GET | `/api/v1/clinical/encounter/<id>/laboratory-results` |

## DoD

Paciente solo ve/sync su persona; staff vía `EncounterAccessService`.
