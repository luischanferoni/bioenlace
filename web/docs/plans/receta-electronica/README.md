# Plan — Receta electrónica (Argentina)

| Campo | Valor |
|-------|--------|
| Slug | `receta-electronica` |
| Estado | En ejecución — Fase 1 |
| Dueño | Equipo clínico / API |
| Norma referencia | Ley 27.553, Receta Digital MSAL (FHIR RDI `recetaDigitalRegistroRecetaAR`) |

## Índice

- [overview.md](./overview.md) — alcance y modos A/B/C
- [design.md](./design.md) — decisiones del programa
- [phases/00-marco.md](./phases/00-marco.md) — marco normativo y contrato
- [phases/01-dominio-mvp.md](./phases/01-dominio-mvp.md) — Fase 1 (en curso)

## Código (Fase 1)

| Área | Ubicación |
|------|-----------|
| Modelos | `common/models/Clinical/ElectronicPrescription*.php` |
| Servicios | `common/components/Clinical/Prescription/` |
| API | `clinical/ElectronicPrescriptionController` |
| Docs operativas | `web/docs/receta-electronica/` (al cerrar fases) |

## Relacionado

- Legacy: [legacy/his-completo/06-Receta_electronica.md](../legacy/his-completo/06-Receta_electronica.md)
- FHIR clínico: [decisions/fhir-clinical.md](../decisions/fhir-clinical.md)
