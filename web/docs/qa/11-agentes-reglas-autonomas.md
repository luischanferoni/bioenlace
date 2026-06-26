# Agentes autónomos (reglas, sin IA)

[← Índice](./README.md) · Producto: [agentes-autonomos.md](../producto/agentes-autonomos.md) · Checklist: [10-checklist-ejecutable.md](./10-checklist-ejecutable.md)

Pruebas de agentes **v1 por reglas YAML** (flags en `params.php`). Los agentes con IA (C03, D02) quedan fuera de este documento.

---

## Activar flags en entorno de prueba

En `params-local.php` (o params de staging), habilitar según el agente:

| Param | Agente |
|-------|--------|
| `autonomous_agent_reserva_triage_post_cupo_enabled` | A05 |
| `autonomous_agent_post_discharge_followup_enabled` | B02 |
| `autonomous_agent_prescription_rdi_validation_enabled` | E03 |
| `autonomous_agent_internacion_cama_sugerencia_enabled` | F02 |

Otros agentes (A03, B01, B03, E01, E02, A04, A06, A01…) suelen estar activos según despliegue — revisar `params.php` y docs de producto.

---

## A05 — Ruteo post-triage sin cupo

**Precondición:** Triage de reserva con grilla de turnos vacía; flag A05 on.

1. **Vos** completás triage en flujo de reserva como paciente.
2. **El sistema** evalúa `ReservaTriagePostCupoRoutingService` con facts del triage.
3. **AGT-05a:** Urgencia banda A → acción `halt`, sin push alternativo.
4. **AGT-05b:** Crónico sin cupo + async disponible → recomendación `async`, push `RESERVA_TRIAGE_CANAL_ALTERNATIVO`.
5. **AGT-05c:** Segunda ejecución mismo caso → idempotente (no duplica push si ya enviado).

---

## B02 — Seguimiento post-alta

**Precondición:** Alta estructurada de internación; flag B02 on; touchpoints B01 habilitados o `isFollowupChannelEnabled`.

1. **Vos** registrás alta en internación.
2. **El sistema** encola touchpoint en `care_followup_touchpoint_queue`.
3. **AGT-06a:** Existe fila con programa/post-alta según YAML.
4. **AGT-06b:** Respuesta del paciente al touchpoint dispara rama B01.

---

## E03 — Validación receta pre-RDI

**Precondición:** Emisión receta electrónica; flag E03 on.

1. **Vos** emitís receta con datos incompletos (según reglas YAML).
2. **El sistema** bloquea o advierte antes de envío RDI.
3. **AGT-07a:** Receta válida → emisión normal.
4. **AGT-07b:** Receta inválida → mensaje claro, sin persistir emitida.

---

## F02 — Sugerencia de cama

**Precondición:** Ingreso internación; flag F02 on; camas configuradas.

1. **Vos** abrís contexto de ingreso (API o pantalla).
2. **El sistema** incluye `cama_sugerencias` en la respuesta.
3. **AGT-08a:** Sugerencias ordenadas por reglas YAML (sector, sexo, aislamiento…).
4. **AGT-08b:** Sin camas candidatas → lista vacía, sin error 500.

---

## A03 — Lista de espera FIFO

1. **Vos** cancelás un turno con lista de espera activa.
2. **El sistema** notifica al siguiente en FIFO.
3. **AGT-03a:** Orden FIFO respetado con dos pacientes en espera.
4. **AGT-03b:** Sin lista → no hay push waitlist.

---

## B01 / B03 — Cohorte y post-lab

| ID | Trigger | Esperado |
|----|---------|----------|
| AGT-01 | Respuesta touchpoint cohorte | Rama YAML correcta (seguimiento / cierre) |
| AGT-02 | Resultado lab con analito crítico | Clasificación + notificación según reglas |

---

## A04 / A06 / A01 — Agenda inteligente (v1)

| ID | Escenario | Esperado |
|----|-----------|----------|
| AGT-09 | Turno alto riesgo no-show | Push `TURNO_ANTINOSHOW_CONFIRM` |
| AGT-10 | Negociación multicanal sin respuesta 72h | Cierre o escalada staff |
| AGT-11 | Resolución turno con shortlist | Opciones ordenadas por score |
| AGT-12 | Auto-reserva con preferencias | Turno creado si hay match |

---

## Tests unitarios relacionados

```bash
cd web
vendor/bin/codecept run unit agent/AutonomousAgentRuleEngineTest
vendor/bin/codecept run unit agent/ReservaTriagePostCupoRoutingServiceTest
vendor/bin/codecept run unit agent/InternacionCamaSugerenciaServiceTest
vendor/bin/codecept run unit agent/PostLabClassificationRuleEngineTest
```

Si `DbTestCase` no está disponible en el entorno, los tests puros de reglas (`*RuleEngine*`, `*Scoring*`) deben pasar sin base de datos.

---

## Qué no probar en esta v1

- Redacción de pushes con IA (diferido).
- Clasificación NL de intents (C03).
- Agentes D02 con modelo generativo.
