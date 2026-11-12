# Cron en producción (Hostinger)

Comandos listos para **hPanel → Avanzado → Cron Jobs**.

En Hostinger el campo **Comando** es solo la línea bash (la frecuencia se configura aparte en el panel). **`crontab -l` por SSH puede estar vacío**: los cron del panel no aparecen ahí.

## Estructura `runtime` en este proyecto

No hay `web/runtime/`. Cada app Yii tiene la suya:

| App | Carpeta |
|-----|---------|
| Consola (`php yii …`) | `web/console/runtime/` |
| Frontend web | `web/frontend/runtime/` |
| Admin | `web/admin/runtime/` |

Los **cron** ejecutan la **consola** (`web/yii` → app `console`). Los logs del redirect van en **`console/runtime/logs/`** (ruta absoluta abajo).

## Una sola vez en el servidor

```bash
mkdir -p /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs
chmod 755 /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs
```

Variables de referencia:

| Variable | Valor |
|----------|--------|
| `WEB` | `/home/u257309594/domains/bioenlace.io/repo/web` |
| `YII` | `/home/u257309594/domains/bioenlace.io/repo/web/yii` |
| `PHP` | `/usr/bin/php` |
| `LOG` | `/home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs` |

Probar a mano:

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii turno-notificacion/run >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/turno-notif-cron.log 2>&1
tail -5 /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/turno-notif-cron.log
```

Si falla `php`, probá la versión del panel (`which php`, o `/usr/bin/php82`).

---

## Obligatorios

### Cada minuto (`* * * * *`)

**Turno — notificaciones y cola programada:**

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii turno-notificacion/run >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/turno-notif-cron.log 2>&1
```

**Resúmenes de atención al paciente:**

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii encounter-patient-summary/run >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/encounter-summary-cron.log 2>&1
```

**Codificación clínica diferida** (diagnóstico, motivos, medicación y pedidos):

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii encounter-coding/run 30 >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/encounter-coding-cron.log 2>&1
```

El worker solo procesa jobs cuya `scheduled_at` ya venció. Por defecto se encolan **48 h después del último guardado** (`encounter_coding_delay_hours` en `params.php`); así se codifica cuando el encounter terminó de editarse.

Una edición del encounter **crea un registro nuevo y anula el anterior** (`cancelled`, `entered-in-error` o soft-delete), de modo que cada encounter recibe su propio job. El worker descarta (`SKIPPED`) los jobs del encounter anulado: nunca se codifica un registro que ya no es el vigente. Si por algún motivo se reutiliza el mismo `encounter_id`, el job se reencola con la misma ventana de 48 h.

**Expediente legal staff:**

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii legal-record-export/run >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/legal-export-cron.log 2>&1
```

### Cada 5 minutos (`*/5 * * * *`)

**Adelantamiento por cancelación — avanzar campañas:**

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii turno-advance-offer/run >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/advance-offer-cron.log 2>&1
```

**Adelantamiento — reparar campañas faltantes** (opcional, junto al run o cada 15–30 min):

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii turno-advance-offer/repair >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/advance-offer-cron.log 2>&1
```

**Cohortes / care-pack:**

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii care-pack/run-jobs >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/care-pack-cron.log 2>&1
```

---

## Recomendados

### Cada 30 minutos (`*/30 * * * *`)

**Verificación domicilio RENAPER:**

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii paciente-domicilio/run >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/domicilio-cron.log 2>&1
```

### Cada 15 minutos (`*/15 * * * *`) — opcional

**Respaldo motivos de consulta:**

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii motivos-consulta/procesar-vencidos >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/motivos-vencidos-cron.log 2>&1
```

**Poll Vertex batch** (solo si `care_cohort.vertex_batch.enabled = true`):

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii care-pack/poll-vertex >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/care-pack-poll.log 2>&1
```

---

## Nocturnos (si aplican)

### 00:15 diario (`15 0 * * *`) — downgrade de cupos (entitlements)

Aplica `pending_max_pes` cuando llega el nuevo período (baja de profesionales diferida al mes siguiente):

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii entitlement/apply-pending-downgrades >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/entitlement-downgrade-cron.log 2>&1
```

### 01:00 diario (`0 1 * * *`) — suspender licencias con período vencido

Suspende la cuenta de licencia y pasa el intento a `EXPIRED` cuando `paid_through_at` ya venció. Es el único corte automático de las suscripciones de Mercado Pago: sin este cron, una suscripción cancelada o impaga conserva el acceso. Ver [mercado-pago-checkout-pro.md](mercado-pago-checkout-pro.md).

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii billing-subscription/expire-paid-through >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/billing-expire-cron.log 2>&1
```

### 01:30 diario (`30 1 * * *`) — purgar borradores de alta vencidos

Elimina borradores `EXPIRED` con más de 30 días de retención. Los checkouts pendientes no se tocan.

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii commercial-signup/purge-drafts >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/commercial-signup-cron.log 2>&1
```

### 02:00 diario (`0 2 * * *`) — laboratorio LIS

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii laboratory-sync/lote 100 0 >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/lab-sync-cron.log 2>&1
```

### 00:45 diario (`45 0 * * *`) — agregado de telemetría de IA

Agrega el JSONL del día anterior (`@common/runtime/ai-usage/ai-usage-*.jsonl`) en `ai_usage_daily`:

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii ai-usage/aggregate --all >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/ai-usage-cron.log 2>&1
```

### 02:30 semanal (domingos) — purga de JSONL de IA

Borra archivos crudos con más de 30 días (el agregado diario se conserva):

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii ai-usage/prune --days=30 >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/ai-usage-cron.log 2>&1
```

### 03:00 diario (`0 3 * * *`) — métricas guardia

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii emergency-guardia/materialize-metrics >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/guardia-metrics-cron.log 2>&1
```

---

## Solo si export FHIR está activo

### Cada 5 minutos (`*/5 * * * *`)

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii clinical-history-exchange/process-outbound >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/fhir-outbound-cron.log 2>&1
```

### 04:00 diario (`0 4 * * *`)

```bash
/usr/bin/php /home/u257309594/domains/bioenlace.io/repo/web/yii clinical-history-exchange/reconcile >> /home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/fhir-reconcile-cron.log 2>&1
```

---

## Resumen (panel Hostinger)

| Frecuencia | Comando Yii | Log (en `console/runtime/logs/`) |
|------------|-------------|----------------------------------|
| Cada minuto | `turno-notificacion/run` | `turno-notif-cron.log` |
| Cada minuto | `encounter-patient-summary/run` | `encounter-summary-cron.log` |
| Cada minuto | `encounter-coding/run` | `encounter-coding-cron.log` |
| Cada minuto | `legal-record-export/run` | `legal-export-cron.log` |
| Cada 5 min | `turno-advance-offer/run` | `advance-offer-cron.log` |
| Cada 15–30 min (opc.) | `turno-advance-offer/repair` | `advance-offer-cron.log` |
| Cada 5 min | `care-pack/run-jobs` | `care-pack-cron.log` |
| Cada 30 min | `paciente-domicilio/run` | `domicilio-cron.log` |
| 00:15 diario | `entitlement/apply-pending-downgrades` | `entitlement-downgrade-cron.log` |
| 00:45 diario | `ai-usage/aggregate` | `ai-usage-cron.log` |
| 02:30 semanal | `ai-usage/prune` | `ai-usage-cron.log` |
| 01:00 diario | `billing-subscription/expire-paid-through` | `billing-expire-cron.log` |
| 01:30 diario | `commercial-signup/purge-drafts` | `commercial-signup-cron.log` |

Ruta base logs: `/home/u257309594/domains/bioenlace.io/repo/web/console/runtime/logs/`

Más contexto: [asistencia-cohortes.md](../producto/asistencia-cohortes.md), [turnos.md](../producto/turnos.md).
