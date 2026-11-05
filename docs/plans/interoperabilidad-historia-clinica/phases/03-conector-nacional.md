# Fase 3 — Conector nacional (endpoint definitivo)

## Objetivo

Implementar el **único hueco funcional** pendiente: `HttpNationalClinicalHistoryConnector::submitEncounterBundle()` con HTTP real.

## Patrón (igual que receta RDI)

| Pieza | Ubicación |
|-------|-----------|
| Contrato | `ClinicalHistoryExchangeConnector` |
| Implementación | `HttpNationalClinicalHistoryConnector` |
| Registry | `ClinicalHistoryExchangeRegistry` |
| Orquestación post-mapa | `ClinicalHistoryOutboundProcessorService` |

## Activación

En `frontend/config/params-local.php` o `common/config/params-local.php`:

```php
'clinicalHistoryExchange' => [
    'enabled' => true,
    'default' => 'nacional-fhir',
    'connectors' => [
        'nacional-fhir' => [
            'enabled' => true,
            'baseUrl' => 'https://…', // TBD contrato
            'tokenUrl' => 'https://…',
            'clientId' => '…',
            'clientSecret' => '…',
            'submitPath' => '/fhir/Bundle', // TBD
            'statusPath' => '/fhir/Bundle/{id}/_status', // TBD polling acuse
        ],
    ],
],
```

## Comportamiento esperado del POST

1. Obtener token OAuth (cachear en memoria / Yii cache TTL).
2. `POST` `Content-Type: application/fhir+json`.
3. Parsear respuesta: `external_id`, `status`, `OperationOutcome` en error.
4. Persistir `external_id` en job + evento auditoría `ENVIADO`.

## Checklist Fase 3

- [ ] URL y credenciales del entorno homologación
- [x] Implementar POST + parseo respuesta
- [x] Manejo 401 refresh token (reintento una vez)
- [ ] Piloto con un efector (`allowed_efector_ids`)
- [ ] Runbook operaciones (reprocesar `MUERTO`)

## Estado actual

`HttpNationalClinicalHistoryConnector` implementa **OAuth client credentials** + **POST** `application/fhir+json`.

Falta únicamente configurar en homologación:

- `baseUrl`, `tokenUrl`, `clientId`, `clientSecret`
- **`statusPath`** — path GET de estado (placeholder `{id}`); vacío = sin reconcile

Sin credenciales reales el conector sigue con `enabled => false` y el flujo usa `null` → jobs `OMITIDO`.
