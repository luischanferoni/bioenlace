# Fase 4 — Operación en producción

Guía para activar y mantener la integración NIS ↔ Bioenlace.

## Prerrequisitos

1. Migraciones aplicadas hasta `m260706_140001`.
2. Catálogo `integration_fhir_service_code` poblado para los códigos `HealthcareService` del efector.
3. Al menos un `integration_schedule_link` en `verified` por PES que recibirá citas.
4. CUIL cargado en profesionales con PES clínico.

## Configuración

`common/config/params-local.php` (o equivalente del entorno):

```php
return [
    'fhirSchedulingInbound' => [
        'enabled' => true,
        'default' => 'msal-nis',
        'outbound' => [
            'enabled' => true,
        ],
        'connectors' => [
            'msal-nis' => [
                // Descomentar cuando NIS exija OAuth:
                // 'tokenUrl' => 'https://…',
                // 'clientId' => '…',
                // 'clientSecret' => '…',
            ],
        ],
    ],
];
```

## Cron sugerido

Ajustar ruta al directorio `web` del deploy.

```cron
# Pull incremental cada 10 minutos
0,10,20,30,40,50 * * * * cd /var/www/bioenlace/web && php yii fhir-scheduling-inbound/pull 50 >> /var/log/bioenlace-fhir-pull.log 2>&1

# Reintento push saliente (por si falló red en hook en tiempo real)
5 * * * * cd /var/www/bioenlace/web && php yii fhir-scheduling-inbound/push-outbound 100 >> /var/www/bioenlace-fhir-push.log 2>&1

# Reconciliación links stale — diario 03:00
0 3 * * * cd /var/www/bioenlace/web && php yii fhir-scheduling-inbound/reconcile-schedule-links 200 >> /var/www/bioenlace-fhir-reconcile.log 2>&1
```

Los hooks en tiempo real cubren la mayoría de cancelaciones/atenciones; `push-outbound` es red de seguridad.

## Secuencia de puesta en marcha

1. `php yii migrate --migrationPath=@common/migrations`
2. Habilitar params (`enabled` + `outbound.enabled`).
3. Staff: mapear códigos servicio FHIR → `id_servicio`.
4. Staff: onboarding Schedule → PES (`preview` + `confirmar`).
5. `php yii fhir-scheduling-inbound/pull 50` — verificar turnos espejo en BD.
6. Probar cancelación/atención local y confirmar `fhir_status` + estado en NIS.

## Logs

| Categoría Yii | Contenido |
|---------------|-----------|
| `fhir-scheduling-inbound` | Errores pull, sync, conector HTTP |
| `fhir-scheduling-outbound` | Push status, warnings de hook |

## Troubleshooting

### Pull devuelve 0 citas

- NIS puede no tener `Appointment` públicos sin filtro; coordinar con MSAL filtros (`actor`, `date`, `identifier`).
- Verificar `integration_fhir_sync_state.last_cursor`: si quedó adelantado con datos vacíos, puede filtrar todo; resetear `last_cursor` a `NULL` para reprocesar (con cuidado en prod).

### Turno espejo sin PES (`pes_resolution_trust = unresolved`)

- Falta onboarding Schedule o catálogo servicio.
- Actores FHIR no matchean CUIL/SISA/código (typo en catálogo o dato faltante en BD).

### Push outbound falla HTTP 4xx/5xx

- Revisar permisos OAuth si NIS lo activó.
- Confirmar que `external_appointment_id` sigue existiendo en NIS.
- Algunos servidores HAPI rechazan transiciones de estado inválidas; revisar matriz FHIR R4.

### Link marcado `stale`

- Actores del `Schedule` cambiaron en NIS (otro profesional, otro servicio).
- Staff debe re-ejecutar preview + confirmar vínculo.

## Tests locales

```bash
cd web
vendor/bin/codecept run unit common/tests/unit/integrations/scheduling/
```

Golden tests con Bundle NIS: pendiente cuando exista fixture acordado.
