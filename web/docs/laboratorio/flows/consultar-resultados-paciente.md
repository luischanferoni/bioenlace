# Consultar resultados de laboratorio (paciente)

## Objetivo

Permitir que el paciente autenticado vea sus informes de laboratorio ya persistidos en Bioenlace (copia normalizada del LIS externo), con detalle de analitos por informe.

## Actores

- Paciente (app móvil, asistente).
- Sistema: lectura en `diagnostic_report` / `observation`.

## Anclas

| Paso | Método / componente |
|------|---------------------|
| API UI (listado) | `LaboratoryResultController::actionMisResultadosComoPaciente` — `GET /api/v1/clinical/laboratory-result/mis-resultados-como-paciente` |
| Permiso RBAC (ApiGhost) | `/api/clinical/laboratory-result/mis-resultados-como-paciente` (singular `laboratory-result`, **sin** `/api/v1`) |
| Consulta | `LaboratoryResultQueryService::listForPersona` (**solo BD local**, sin llamar al LIS) |
| Intent | `laboratorio.ver-resultados-como-paciente` (2 pasos) |
| Lista UI | `clinical.laboratory-result.mis-resultados-como-paciente` |
| Detalle UI | `clinical.laboratory-result.ver-informe-como-paciente` |
| PDF | `GET clinical/laboratory-result/descargar-pdf-como-paciente?report_id=` |
| RBAC | `mis-resultados-como-paciente`, `ver-informe-como-paciente`, `descargar-pdf-como-paciente` |

---

## Secuencia

1. Cliente con JWT de paciente (`idPersona`; no requiere `set-session` operativo).
2. Asistente: `ver_listado` → `GET mis-resultados-como-paciente` (lista, confirmar ítem → `draft.report_id`).
3. `ver_detalle` → `GET ver-informe-como-paciente?report_id=` (analitos, conclusión, botón PDF).
4. Descarga: `GET descargar-pdf-como-paciente` (PDF generado en servidor con mPDF).

Si el listado está vacío, los informes aún no fueron importados; la ingesta es responsabilidad de operaciones/cron ([ingesta-cron.md](./ingesta-cron.md)).

## Contrato

- Límite por defecto: **50** informes (`LaboratoryResultQueryService`).
- `encounterId` puede ser `null` si el informe no se vinculó a una consulta.

## Errores

| HTTP | Causa |
|------|--------|
| 400 | Usuario sin `idPersona`. |
| 401/403 | Token inválido o sin permiso RBAC. |

## Relacionado

- [ingesta-cron.md](./ingesta-cron.md)
- [intents-laboratorio-paciente.md](./intents-laboratorio-paciente.md)
