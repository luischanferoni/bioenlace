# Solicitar / actualizar resultados de laboratorio (paciente)

## Objetivo

Que el paciente dispare la sincronización pull desde el LIS externo (FHIR) para traer informes nuevos o actualizados a Bioenlace (“pedir mis resultados” = actualizar desde el laboratorio).

## Actores

- Paciente autenticado (app / asistente).
- Conector LIS (`LabConnectorRegistry`).
- Operaciones (alternativa): `php yii laboratory-sync/persona` — [ingesta-pull.md](./ingesta-pull.md).

## Anclas

| Paso | Método / componente |
|------|---------------------|
| API JSON | `LaboratoryResultController::actionSincronizar` — `POST /api/v1/clinical/laboratory-result/sincronizar` |
| API UI | `LaboratoryResultController::actionSincronizarComoPaciente` — `GET\|POST /api/v1/clinical/laboratory-result/sincronizar-como-paciente` |
| Servicio | `LaboratoryIngestService::syncForPersona` |
| Intent | `laboratorio.sincronizar-resultados-como-paciente` |
| RBAC | `/api/clinical/laboratory-result/sincronizar`, `/api/clinical/laboratory-result/sincronizar-como-paciente` |

---

## Secuencia

1. Paciente inicia “Actualizar resultados” en asistente (`laboratorio.sincronizar-resultados-como-paciente`).
2. Mini-UI de confirmación (`sincronizar-como-paciente.json`); opcional `connector` en body.
3. `flow_submit` → `POST sincronizar-como-paciente`.
4. Respuesta `ui_submit_result` con `imported`, `skipped`, `errors`.
5. Refrescar listado vía [consultar-resultados-paciente.md](./consultar-resultados-paciente.md).

## Parámetros

| Parámetro | Descripción |
|-----------|-------------|
| `connector` | Clave en `params['laboratoryConnectors']['connectors']`; si se omite, usa `default`. |

## Configuración

Credenciales en `params-local.php` bajo `laboratoryConnectors` (ver [ingesta-pull.md](./ingesta-pull.md)).

## Relacionado

- [consultar-resultados-paciente.md](./consultar-resultados-paciente.md)
- [intents-laboratorio-paciente.md](./intents-laboratorio-paciente.md)
