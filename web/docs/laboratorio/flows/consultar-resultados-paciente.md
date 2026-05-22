# Consultar resultados de laboratorio (paciente)

## Objetivo

Permitir que el paciente autenticado vea sus informes de laboratorio ya persistidos en Bioenlace (copia normalizada del LIS externo), con detalle de analitos por informe.

## Actores

- Paciente (app móvil, asistente).
- Sistema: lectura en `diagnostic_report` / `observation`.

## Anclas

| Paso | Método / componente |
|------|---------------------|
| API JSON | `LaboratoryResultController::actionMisResultados` — `GET /api/v1/clinical/laboratory-results/mis-resultados` |
| API UI | `LaboratoryResultController::actionMisResultadosComoPaciente` — `GET /api/v1/clinical/laboratory-results/mis-resultados-como-paciente` |
| Consulta | `LaboratoryResultQueryService::listForPersona` |
| Intent | `laboratorio.ver-resultados-como-paciente` |
| RBAC | `/api/clinical/laboratory-results/mis-resultados`, `/api/clinical/laboratory-results/mis-resultados-como-paciente` |

---

## Secuencia

1. Cliente con JWT de paciente (`idPersona`; no requiere `set-session` operativo).
2. Asistente: intent `laboratorio.ver-resultados-como-paciente` → `open_ui` `clinical.laboratory-results.mis-resultados-como-paciente`.
3. `GET mis-resultados-como-paciente` devuelve `ui_definition` con lista de informes (`observations[]` en el payload de negocio al listar vía API JSON).
4. Si el listado está vacío, ofrecer [solicitar-resultados-paciente.md](./solicitar-resultados-paciente.md).

## Contrato

- Límite por defecto: **50** informes (`LaboratoryResultQueryService`).
- `encounterId` puede ser `null` si el informe no se vinculó a una consulta.

## Errores

| HTTP | Causa |
|------|--------|
| 400 | Usuario sin `idPersona`. |
| 401/403 | Token inválido o sin permiso RBAC. |

## Relacionado

- [solicitar-resultados-paciente.md](./solicitar-resultados-paciente.md)
- [intents-laboratorio-paciente.md](./intents-laboratorio-paciente.md)
