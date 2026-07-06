# Plan — Agendamiento FHIR entrante (HAPI NIS)

| Campo | Valor |
|-------|--------|
| Slug | `fhir-scheduling-inbound` |
| Estado | **Implementado** (pendiente: activación prod, golden tests con datos NIS) |
| Servidor FHIR | [https://nis.msalsgo.gob.ar/fhir](https://nis.msalsgo.gob.ar/fhir) |

## Índice

| Doc | Contenido |
|-----|-----------|
| [overview.md](./overview.md) | Alcance y entregables |
| [design.md](./design.md) | Confianza PES, fail-closed, flujos entrante/saliente |
| [phases/00-marco.md](./phases/00-marco.md) | Contrato HTTP con NIS |
| [phases/01-datos-confianza.md](./phases/01-datos-confianza.md) | Checklist por fase |
| [phases/02-resolver-pes.md](./phases/02-resolver-pes.md) | Resolver + onboarding Schedule |
| [phases/03-sync-appointments.md](./phases/03-sync-appointments.md) | Pull/push Appointment ↔ turnos |
| [phases/04-operacion.md](./phases/04-operacion.md) | Activación, cron, troubleshooting |

## Código

| Área | Ubicación |
|------|-----------|
| Conector NIS | `common/components/Domain/Integrations/Scheduling/Connector/MsalNisFhirSchedulingConnector.php` |
| Contrato HTTP | `…/Contract/FhirSchedulingInboundConnector.php` |
| Pull | `FhirSchedulingInboundPullService`, `TurnoInboundSyncService` |
| Push estados | `FhirAppointmentOutboundSyncService`, `TurnoFhirOutboundNotifier` |
| Resolver PES | `FhirSchedulePesResolver`, `FhirScheduleActorExtractor` |
| Catálogos | `FhirHealthcareServiceCodeCatalog`, `IntegrationScheduleLinkService` |
| Onboarding UI | `Organization/Service/ProfesionalEfectorServicio/FhirScheduleOnboardingUiService.php` |
| Mapper estados | `Mapper/FhirAppointmentStatusMapper.php` |
| Consola | `console/controllers/FhirSchedulingInboundController.php` |
| Params | `common/config/params.php` → `fhirSchedulingInbound` |
| Tests unitarios | `common/tests/unit/integrations/scheduling/` |

## Migraciones

| Migración | Contenido |
|-----------|-----------|
| `m260706_130000_fhir_scheduling_trust_data` | `personas.cuil`, `integration_fhir_service_code`, `integration_schedule_link` |
| `m260706_130001_api_fhir_scheduling_trust_rbac` | RBAC catálogo CUIL / códigos servicio |
| `m260706_140000_turnos_fhir_inbound_columns` | columnas espejo en `turnos`, `integration_fhir_sync_state` |
| `m260706_140001_api_fhir_scheduling_inbound_rbac` | RBAC onboarding Schedule HAPI |

```bash
php yii migrate --migrationPath=@common/migrations
```

## Activación rápida

En `params-local.php`:

```php
return [
    'fhirSchedulingInbound' => [
        'enabled' => true,
        'outbound' => ['enabled' => true],
        // OAuth cuando NIS lo exija:
        // 'connectors' => ['msal-nis' => ['tokenUrl' => '…', 'clientId' => '…', 'clientSecret' => '…']],
    ],
];
```

Consola:

```bash
php yii fhir-scheduling-inbound/pull 50
php yii fhir-scheduling-inbound/push-outbound 100
php yii fhir-scheduling-inbound/reconcile-schedule-links
```

Detalle operativo: [phases/04-operacion.md](./phases/04-operacion.md).
