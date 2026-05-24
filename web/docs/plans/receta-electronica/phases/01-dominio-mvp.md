# Fase 1 — Dominio receta emitida (MVP modo A)

## Objetivo

Borrador desde `medication_request` del encounter → emitir → consultar → anular. Sin repositorio nacional ni firma.

## Checklist implementación

- [x] Plan en `plans/receta-electronica/`
- [x] Migración `electronic_prescription`, `electronic_prescription_item`, `electronic_prescription_event`
- [x] Enum `PrescriptionLegalStatus`, `PrescriptionEventType`
- [x] Modelos AR + DTOs
- [x] `ElectronicPrescriptionService` + `FhirRecetaDigitalBundleMapper` (snapshot)
- [x] `ElectronicPrescriptionController` + rutas `main.php`
- [x] RBAC rutas ApiGhost
- [ ] Tests automatizados (opcional siguiente PR)
- [ ] UI médico / paciente (Fase 1b o cliente Flutter)

## API Fase 1

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/clinical/encounter/<id>/electronic-prescription/crear-borrador` | Borrador desde medication requests activos |
| GET | `/api/v1/clinical/encounter/<id>/electronic-prescriptions` | Listado del encounter |
| GET | `/api/v1/clinical/electronic-prescription/<id>` | Detalle |
| POST | `/api/v1/clinical/electronic-prescription/<id>/emitir` | Pasa a `issued` |
| POST | `/api/v1/clinical/electronic-prescription/<id>/anular` | Pasa a `cancelled` |
| GET | `/api/v1/clinical/electronic-prescription/mis-recetas-como-paciente` | Listado paciente |

## Próximo paso (Fase 1b)

UI JSON asistente o pantalla Flutter médico para emitir desde consulta abierta.
