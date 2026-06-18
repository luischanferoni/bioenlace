# Asistencia por cohortes — producto y operación

Packs reutilizables de **preguntas pre-consulta**, **seguimiento post-atención** y **educación**, generados por cohorte clínica (~90 % de similitud) y consumidos sin IA en runtime (salvo delta puntual).

---

## Configuración por capa

| Capa | Rol | Archivos |
|------|-----|----------|
| **common** | Defaults **frontend + admin** | `common/config/params.php`, `params-care-cohort.php` |
| **frontend** | API, asistente, móvil — cohortes **on** | merge: common + `frontend/config/params.php` |
| **console** | Cron — **independiente** | solo `console/config/params.php` + `params-local.php` |
| **admin** | Hereda common | merge: common + `admin/config/params.php` |

### Cadena de merge

| App | Params |
|-----|--------|
| Frontend / admin | `ArrayHelper::merge(common/params, common/params-local, app/params, app/params-local)` |
| **Consola** | `ArrayHelper::merge(console/params, console/params-local)` — **sin common** |

La consola repite en `console/config/params.php` lo que el cron necesita (`care_cohort`, `ia_proveedor`, `vertex_ai_model`, …). Credenciales GCP: **`console/config/params-local.php`**.

### Activar / desactivar

- **API:** `frontend/config/params.php` → `care_cohort.enabled`
- **Cron:** `console/config/params.php` → `care_cohort.enabled` (independiente de la API)

### Vertex batch (producción)

En **`console/config/params-local.php`**:

```php
'google_cloud_credentials_path' => '...',
'google_cloud_project_id' => '...',
'care_cohort' => [
    'vertex_batch' => [
        'enabled' => true,
        'gcs_bucket' => 'bioenlace-care-batch-prod',
    ],
],
```

Diagnóstico: `php yii care-pack/vertex-status`

---

## Cron — cómo deben correr

Directorio de trabajo: `web/` (donde está `yii`).

### Comando principal (obligatorio)

```bash
php yii care-pack/run-jobs
```

**Qué hace en cada ejecución:**

1. Submit de jobs pendientes a Vertex (si `vertex_batch.enabled` y umbral alcanzado)
2. Poll de batches Vertex ya enviados → materializa `care_cohort_pack`
3. Procesa jobs **sync** (`IAManager`) hasta el límite del argumento
4. Procesa touchpoints de seguimiento vencidos (push al paciente)

**Frecuencia recomendada:** cada **5 minutos**.

Ejemplo crontab (usuario del hosting):

```cron
*/5 * * * * cd /home/.../repo/web && /usr/bin/php yii care-pack/run-jobs >> runtime/logs/care-pack-cron.log 2>&1
```

### Poll Vertex (refuerzo, opcional)

```bash
php yii care-pack/poll-vertex
```

Solo consulta batches ya enviados. Útil si el poll del `run-jobs` no alcanza por timeout del hosting.

**Frecuencia:** cada **15 minutos** si usás Vertex batch.

```cron
*/15 * * * * cd /home/.../repo/web && /usr/bin/php yii care-pack/poll-vertex >> runtime/logs/care-pack-poll.log 2>&1
```

### Seguimiento aislado (opcional)

```bash
php yii care-pack/process-followups
```

Equivalente al paso followup de `run-jobs`. Solo si querés separar cargas; en la práctica **no hace falta** si `run-jobs` corre cada 5 min.

### Relación con otros cron

| Cron | Relación |
|------|----------|
| `turno-notificacion/run` | Motivos de consulta (otro batch IA); **independiente** de care-pack |
| `care-pack/run-jobs` | Generación packs + followup cohorte |

---

## Flujo runtime (sin cron en request)

1. Turno con encounter → hook asistencia pre-consulta
2. Cierre encounter → hook seguimiento + educación
3. Si falta pack para `cohort_key` → fila en `care_pack_job`
4. Cron genera pack (sync o Vertex)
5. Paciente: API `/api/v1/care-packs/assistance|followup`
6. Staff: bloque cohorte en historia clínica

---

## Telemetría IA

| Modo | Contexto `AICostTracker` |
|------|--------------------------|
| Sync (cron) | `care-pack-assistance-batch`, `care-pack-followup-batch`, `care-pack-education-batch` |
| Vertex batch | `care-pack-vertex-batch` |

Catálogo: [catalogo-usos-ia.md](./catalogo-usos-ia.md)

---

## Código

| Área | Ubicación |
|------|-----------|
| Dominio | `common/components/Domain/Clinical/CareCohort/` |
| API | `frontend/modules/api/v1/controllers/CarePacksController.php` |
| Consola | `console/controllers/CarePackController.php` |
