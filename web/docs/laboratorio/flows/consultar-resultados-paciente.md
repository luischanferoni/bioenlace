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
| Permiso RBAC (ApiGhost) | `/api/clinical/laboratory-result/mis-resultados-como-paciente` (singular `laboratory-result`, **sin** `/api/v1`) |
| Consulta | `LaboratoryResultQueryService::listForPersona` |
| Intent | `laboratorio.ver-resultados-como-paciente` (2 pasos) |
| Lista UI | `clinical.laboratory-results.mis-resultados-como-paciente` |
| Detalle UI | `clinical.laboratory-results.ver-informe-como-paciente` |
| PDF | `GET clinical/laboratory-results/descargar-pdf-como-paciente?report_id=` |
| RBAC | `mis-resultados*`, `ver-informe-como-paciente`, `descargar-pdf-como-paciente` |

---

## Secuencia

1. Cliente con JWT de paciente (`idPersona`; no requiere `set-session` operativo).
2. Asistente: `ver_listado` → `GET mis-resultados-como-paciente` (lista, confirmar ítem → `draft.report_id`).
3. `ver_detalle` → `GET ver-informe-como-paciente?report_id=` (analitos, conclusión, botón PDF).
4. Descarga: `GET descargar-pdf-como-paciente` (PDF generado en servidor con mPDF).
5. Si el listado está vacío, ofrecer [solicitar-resultados-paciente.md](./solicitar-resultados-paciente.md).

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
