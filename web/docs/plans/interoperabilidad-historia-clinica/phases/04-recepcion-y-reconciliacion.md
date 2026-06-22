# Fase 4 — Recepción y reconciliación

## Entrante (futuro)

Hoy Bioenlace ya **recibe** FHIR en dominios acotados:

| Fuente | Mecanismo | Carpeta |
|--------|-----------|---------|
| Laboratorio LIS | Pull cron / manual | `Integrations/Laboratory/` |
| MPI paciente | API consulta | `Integrations/Mpi/` |

Para **historia clínica entrante** desde el Estado (si aplica):

| Opción | Descripción |
|--------|-------------|
| **Webhook** | `POST /api/v1/integrations/clinical-history/inbound` (a diseñar) |
| **Polling** | Cron consulta estado por `external_id` |

## Acuse de export saliente

Tras Fase 3, si el nacional no devuelve id en línea:

1. Job queda `ENVIADO` con `external_id` null.
2. Cron `reconcile` consulta API de estado (TBD).
3. Actualiza job o marca discrepancia.

## Actualización de documento

Si el encuentro se **corrige** post-envío (política producto):

- Fase 4+: nuevo job `exchange_profile` = `encounter-document-v1-amendment` o reemplazo según norma.
- **No** reenvío automático en Fase 1.

## Checklist Fase 4

- [ ] Definir si el receptor expone webhook o solo polling
- [ ] Endpoint inbound + validación Bundle
- [ ] Conciliación diaria
- [ ] Dashboard operaciones (jobs `MUERTO` / sin acuse)
