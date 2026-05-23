# Ingesta programada — laboratorio (cron)

## Objetivo

Traer informes del LIS externo a Bioenlace **sin** que el paciente dispare la sincronización desde la app. El paciente solo **consulta** lo ya persistido ([consultar-resultados-paciente.md](./consultar-resultados-paciente.md)).

## Actores

- **Cron / scheduler** del servidor (o operaciones manual).
- `LaboratoryIngestService` + `LaboratorySyncBatchService`.
- Conector LIS (`LabConnectorRegistry`).

## Comandos

| Comando | Uso |
|---------|-----|
| `php yii laboratory-sync/persona <id_persona> [connector]` | Una persona (soporte, reproceso) |
| `php yii laboratory-sync/lote [limit] [offset] [connector] [soloConUsuario]` | Lote paginado para cron |

### Ejemplo cron (cada noche, lotes de 100)

```bash
# Offset 0, 100, 200… hasta que processed=0
php yii laboratory-sync/lote 100 0
php yii laboratory-sync/lote 100 100
```

Parámetros del lote:

| Parámetro | Default | Descripción |
|-----------|---------|-------------|
| `limit` | 50 | Personas por ejecución (máx. 500) |
| `offset` | 0 | Paginación (`ORDER BY id_persona`) |
| `connector` | (vacío) | Clave en `params['laboratoryConnectors']` |
| `soloConUsuario` | 1 | `1` = solo personas con `id_user` (cuentas app) |

Salida JSON: `processed`, `imported_total`, `skipped_total`, `errors`, detalle por `id_persona`.

## Configuración

Credenciales en `params-local.php` → `laboratoryConnectors` (ver [ingesta-pull.md](./ingesta-pull.md)).

## Fuera de alcance (producto paciente)

- `POST /api/v1/clinical/laboratory-result/sincronizar` — **retirado**
- Intent `laboratorio.sincronizar-resultados-como-paciente` — **retirado**

## Relacionado

- [ingesta-pull.md](./ingesta-pull.md)
- [consultar-resultados-paciente.md](./consultar-resultados-paciente.md)
