# Ingesta pull — laboratorio (operaciones)

> **Nota:** El flujo de “actualizar resultados” desde el **asistente / app del paciente** fue retirado. La ingesta queda en **consola y cron**. Ver [ingesta-cron.md](./ingesta-cron.md).

## Objetivo

Traer informes nuevos o actualizados del LIS externo (FHIR) hacia Bioenlace para que el paciente los vea en el listado local.

## Actores

- Operaciones / cron (`laboratory-sync/lote`).
- Soporte puntual (`laboratory-sync/persona`).
- `LaboratoryIngestService::syncForPersona`.

## Anclas

| Paso | Componente |
|------|------------|
| Servicio | `LaboratoryIngestService::syncForPersona` |
| Lote | `LaboratorySyncBatchService` + `php yii laboratory-sync/lote` |
| Una persona | `php yii laboratory-sync/persona <id_persona> [connector]` |
| Demo sin LIS | `php yii clinical-seed/laboratory-demo <id_persona>` |

## Secuencia (lote)

1. Cron invoca `laboratory-sync/lote` con `limit` / `offset`.
2. Por cada persona con documento (y opcionalmente `id_user`): pull FHIR → upsert `diagnostic_report` / `observation`.
3. El paciente consulta con [consultar-resultados-paciente.md](./consultar-resultados-paciente.md) (solo BD).

## Relacionado

- [ingesta-cron.md](./ingesta-cron.md)
- [ingesta-pull.md](./ingesta-pull.md)
