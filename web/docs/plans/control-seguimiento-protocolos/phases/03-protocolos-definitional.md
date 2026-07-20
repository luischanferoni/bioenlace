# Fase 3 — Protocolos definitional (PlanDefinition-lite)

## Objetivo

Introducir catálogo de **protocolos de cuidado** en metadata, resolubles por Condition (y luego por perfil en Fase 4), que exponen las mismas clases de acción que el tratamiento activo (turno, async, etc.) con **draft prefilled**.

## Forma propuesta (YAML)

Ubicación tentativa:

`web/common/components/Domain/Clinical/metadata/care_protocols.yaml`

(o `Scheduling/metadata/` si se prefiere colocalizar con intake; preferible **Clinical** por PlanDefinition).

```yaml
version: "1"
protocols:
  - id: hta_control_periodico
    title: Control de hipertensión
    fhir_kind: PlanDefinition   # documental; no export aún
    applies:
      condition_codes:          # CIE-10 / SNOMED según Condition.code_system
        - "I10"
      clinical_status: [active, relapse]
    actions:
      - code: solicitar_turno
        label: Pedir turno de control
        draft:
          triage_raiz: seguimiento_cronico
          tipo_atencion: presencial
          # servicio / preferencias según reglas existentes
      - code: consulta_evolucion
        label: Contar evolución
        draft:
          seguimiento_necesidad: contar_evolucion
          # intake_tipo / care_plan_id si se vincula
```

## Servicio

`CareProtocolCatalogService` + `CareProtocolMatcherService`:

- Input: persona, Condition[], opcional CarePlans[], perfil demográfico.
- Output: lista `{ protocol_id, title, actions[] }` sin lógica en el controller.

Registry de handlers de acción solo por `action.code` genérico (reutilizar submit async / turnos), no por `protocol_id` en orquestadores.

## Relación con CarePlan

| Caso | Comportamiento v1 |
|------|-------------------|
| Ya hay CarePlan para esa condición | Preferir ancla CarePlan (acciones intake); protocolo como complemento o oculto |
| Solo Condition | Mostrar protocolo |
| Protocolo aceptado | Draft prefilled; **no** crear CarePlan automático (salvo decisión contraria en Fase 0) |

## Checklist

- [x] YAML + parser + cache reset en tests.
- [x] Match por código Condition (I10, E11.x, J45).
- [x] Hub (Fase 2) consume acciones de protocolo (`listConditionActionItems` / `resolveConditionAction`).
- [x] Ningún `if ($protocolId === '…')` en ChatOrchestrator / controllers gruesos.
- [x] Test de catálogo + matcher.

## Hecho en código (Fase 3)

- `Clinical/metadata/care_protocols.yaml` (HTA, diabetes, asma de ejemplo)
- `CareProtocolCatalogService` + `CareProtocolMatcherService`
- Condición en hub: ancla por código CIE; acciones del protocolo o fallback
- Flow: `protocol_action_outcome` → modalidad / captura_mensaje

