# Roadmap asistente (fases)

## Objetivo

Historial de fases del asistente y árbol objetivo de componentes.

## Actores

- Equipo backend / producto.

## Anclas

| Área | Ruta |
|------|------|
| Código | `common/components/Assistant/` |
| Contrato vigente | [ASSISTANT_ENVELOPE_CONTRACT.md](./ASSISTANT_ENVELOPE_CONTRACT.md) |

---

Estado a mayo 2026. Contrato público: `ASSISTANT_ENVELOPE_CONTRACT.md`.

## Fase 1 — Sobre bobo (hecha)

- `message` | `interactive` | `flow` en `POST /api/v1/asistente/enviar`
- `AssistantEnvelope`, `ChatOrchestrator`, web paciente, Flutter paciente

## Fase 2 — Preprocess + routing + canales (hecha)

- `ChatPreprocessService` → `user_goal`, `extractions[]`
- `ChatRouter` → Operational / Conversational / Informational
- `OperationalChannel`: top-K + `IntentClassifier::classifyAmongItems` (sin catálogo completo en IA)

## Fase 3 — Hints (hecha)

- `hint.entity` en YAML + `FlowHintService` + `HintCandidateProvider` (por entidad/draft, no por `action_id`)
- `hints[]` en sobre `flow`; merge en query del `open_ui` (`SubIntentEngine`)

## Fase 4 — Entry points clínicos (hecha, base)

| Entry point | API | Preprocess chat |
|-------------|-----|-----------------|
| `EntryPoints/Chat/` | `asistente/enviar` | Sí |
| `EntryPoints/AppointmentReason/` | `motivos-consulta/*` | No |
| `EntryPoints/ClinicalEncounter/` | `consulta/analizar`, `consulta/guardar` | No |

Pendiente en AppointmentReason: pipeline IA para estructurar motivo (hoy solo persistencia).

## Fase 5 — Limpieza (parcial)

| Ítem | Estado |
|------|--------|
| Preprocess también con `intent_id` + `content` (hints en pasos del flow) | Hecho |
| `InformationalChannel` devuelve sobre v3 | Hecho |
| Motores internos sin `kind` legacy (`intent_flow`, etc.) | Hecho (`IntentEngine` + `OperationalChannel` → `AssistantEnvelope`) |
| Clientes sin `expandAssistantEnvelope` (leer `session`/`step` directo) | Hecho (web paciente + Flutter paciente) |
| App médico al sobre v3 | Hecho (`AccionesScreen` + `AsistenteService` en `shared`) |
| Tests automatizados preprocess/hints/flow | Pendiente |
| Presentación list/search explícita en servidor | Pendiente (cliente decide hoy) |

## Árbol objetivo

```text
Assistant/
  EntryPoints/
    Chat/              → asistente/enviar
    AppointmentReason/ → motivos-consulta
    ClinicalEncounter/ → consulta analizar/guardar
  IntentEngine/        → transición → Operational
  SubIntentEngine/     → YAML flows
```
