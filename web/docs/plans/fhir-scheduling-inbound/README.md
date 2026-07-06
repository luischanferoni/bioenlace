# Plan — Agendamiento FHIR entrante (HAPI NIS)

| Campo | Valor |
|-------|--------|
| Slug | `fhir-scheduling-inbound` |
| Estado | Fases 2–3 en curso |
| Servidor FHIR | [https://nis.msalsgo.gob.ar/fhir](https://nis.msalsgo.gob.ar/fhir) |

## Índice

| Doc | Contenido |
|-----|-----------|
| [overview.md](./overview.md) | Alcance |
| [design.md](./design.md) | Confianza PES, fail-closed |
| [phases/00-marco.md](./phases/00-marco.md) | Contrato NIS |
| [phases/01-datos-confianza.md](./phases/01-datos-confianza.md) | Estado por fase |
| [phases/02-resolver-pes.md](./phases/02-resolver-pes.md) | Resolver + onboarding |
| [phases/03-sync-appointments.md](./phases/03-sync-appointments.md) | Pull → turnos |

## Código

| Área | Ubicación |
|------|-----------|
| Conector NIS | `Integrations/Scheduling/Connector/MsalNisFhirSchedulingConnector.php` |
| Pull / sync | `FhirSchedulingInboundPullService`, `TurnoInboundSyncService` |
| Onboarding | `FhirScheduleOnboardingUiService`, APIs `*-schedule-hapi` |
| Consola | `console/controllers/FhirSchedulingInboundController.php` |
| Params | `common/config/params.php` → `fhirSchedulingInbound` |
| Migraciones | `m260706_130000` … `m260706_140001` |

## Activación

En `params-local.php`:

```php
return [
    'fhirSchedulingInbound' => [
        'enabled' => true,
    ],
];
```

Cron sugerido:

```bash
php yii fhir-scheduling-inbound/pull 50
php yii fhir-scheduling-inbound/reconcile-schedule-links
```
