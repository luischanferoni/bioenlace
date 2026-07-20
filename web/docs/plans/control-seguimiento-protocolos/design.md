# Design — Control/Seguimiento + protocolos

## Decisiones

| Tema | Decisión |
|------|----------|
| Entrada única | Solo `atencion.necesito-atencion` → triage `seguimiento_cronico` (label **Control/Seguimiento**) |
| Intent a retirar | `atencion.consultas-seguimiento-flow` como atajo/entrada; la **lógica** (intake YAML, hydrator, acciones async/turno) se **reutiliza** desde el hub |
| Ancla tratamiento | FHIR **CarePlan** existente (`care_plan`) |
| Ancla diagnóstico | FHIR **Condition** (`clinical_condition`, activos/crónicos) |
| Plantilla de protocolo | FHIR **PlanDefinition** (modelo lite en metadata v1; tabla opcional en fase posterior) |
| Instancia de protocolo | Preferir **CarePlan** (`category` `preventive` / `chronic`) cuando haya plan materializado; si no, el flow opera con `protocol_id` + draft prefilled sin persistir CarePlan aún |
| Acciones | Declarativas (mismo espíritu que `consultas_seguimiento_intake.yaml`): `action_id` / intent params / draft keys — **0 hardcode** de `intent_id` en orquestadores |
| CarePack | **No** es protocolo de control. Sigue siendo contenido IA por cohorte |
| Canal | App paciente (como consultas-seguimiento hoy); web clínica no es canal de este hub |

## Mapa conceptual

```text
Control/Seguimiento (hub)
├── CarePlan activo(s)     → acciones intake (renovar, ajuste, evolución, turno, …)
├── Condition activa/crónica
│     └── Protocolo aplicable (PlanDefinition-lite)
│           → mismas clases de acción (turno / async / …) con draft prefilled
└── Perfil (edad, sexo, …)
      └── Protocolos preventivos sin Condition previa
```

## Capas (sin hardcode)

| Capa | Responsabilidad |
|------|-----------------|
| Intent `necesito-atencion` | Routing a hub cuando `triage_raiz = seguimiento_cronico` |
| UI hub (ui_json / Flutter) | Lista anclas + acciones; genérica |
| Metadata protocolos | Quién aplica + qué acciones + draft defaults |
| Services dominio | Resolver CarePlans, Conditions, match de protocolos, armar draft |
| Persistencia | CarePlan / Condition existentes; `protocol` catalog file o tabla luego |

## API / UI (borrador)

| Pieza | Notas |
|-------|--------|
| Paso triage ya existe | `turnos.reserva-triage-paso` step `raiz` |
| Nuevo (o extender) | `action_id` tipo `atencion.control-seguimiento-hub` → JSON con secciones `care_plans`, `conditions`, `protocols` |
| Acciones | Reusar `open_ui` / `flow_submit` / deep-link al mismo motor de draft que hoy usa consultas-seguimiento |

No inventar endpoints por cada protocolo: un resolver + metadata.

## PRs sugeridos (orden)

1. **Fase 1** — routing Control/Seguimiento → flow absorbido; quitar shortcut del intent viejo; classification.
2. **Fase 2** — hub UI (tratamientos + condiciones) + API listado anclas/acciones.
3. **Fase 3** — YAML protocolos + `CareProtocolCatalogService` + wire Condition → acciones.
4. **Fase 4** — reglas perfil + 1–2 protocolos preventivos de ejemplo.
5. **Fase 5** — docs producto; borrar carpeta del plan.

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Paciente sin CarePlan ni Condition queda vacío | Sección “Control general” → modalidad/turno (comportamiento actual de `seguimiento_cronico`) |
| Duplicar acciones en detalle CarePlan y hub | Misma fuente metadata (`accionesSeguimientoCarePlan` / protocolos) |
| Confundir con CarePack | Naming en UI: “Protocolo” / “Control recomendado”; nunca “CarePack” |
| Classification NL sigue mandando a intent retirado | Actualizar `intent-classification-rules` + aliases en Fase 1 |

## Tests mínimos

- Catalog triage lista Control/Seguimiento.
- Tras elegir `seguimiento_cronico`, el siguiente paso es el hub (no solo modalidad ciega).
- Acciones CarePlan desde hub equivalen a las del detalle.
- Match protocolo por Condition code (fixture).
- Intent viejo no aparece en shortcuts paciente.
