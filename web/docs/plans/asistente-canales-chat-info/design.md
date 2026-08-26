# Design

## Principios

1. **Canal = salida del preprocess IA.** PHP enruta; no reescribe `user_goal`. Si la IA falla → error al usuario, no heurística.
2. **Prompts afirmativos.** Definir qué *sí* está en alcance; `unclear` = no encaja. Evitar listas largas de “qué no es”.
3. **Perímetro Bioenlace.** Une centros con la **salud del paciente que consulta** (y sujetos de representación/tutela formal). Terceros / consejo farmacológico genérico → fuera (`unclear` / descarte).
4. **Casi todo contenido accionable ↔ intent(s).** El CTA respeta RBAC; la visibilidad del artículo debería alinearse al permiso de ejecutar.
5. **0 hardcode de intents en orquestadores.** Prioridades/CTAs en metadata; motores genéricos.

---

## Canales (rename)

| ID destino | Hoy | Rol | Botones |
|------------|-----|-----|---------|
| `conversational_clinical` | `conversational` | Saludo / malestar / charla sobre **su** salud | **Siempre** Solicitar Atención (`atencion.necesito-atencion` u oferta prioritaria RBAC) |
| `informational_conversational` | `informational` | Ayuda de producto; IA **anclada** a `info_content` | CTA a `intent_id`(s) del artículo |
| `ambiguous_conversational` | *(nuevo; hoy `unclear` seco)* | Dominio no claro | Preguntas/botones **predefinidos** (sin 2ª IA) para encauzar al canal correcto |
| `operational` | `operational` | Trámite concreto | Flow / UI del intent matcheado |
| `meta` / `in_flow_question` | iguales | Asistente / flujo en curso | Según caso |

Migración: alias interno o rename de strings en GOALS + router + prompts en la misma fase.

### Ejemplo de encauzamiento (`ambiguous_conversational`)

Sin segunda IA generativa:

- ¿Es sobre **tu** salud o sobre cómo funciona la app?
- ¿Querés **hacer** un trámite (turno/cancelar) o solo **entender** cómo se hace?
- ¿Es sobre un menor / representante, o sobre tu atención?

La respuesta fija el canal del siguiente turno y etiqueta el hilo.

---

## Preprocess

- Prompt: alcance válido + goals (ya encaminado en código).
- Sin `resolveUserGoal` / sin forzar operational por regex de turno / sin forzar clinical por historial.
- Mejoras futuras del prompt solo por metadata YAML (no reglas PHP de canal).

---

## Prompts y carpetas metadata

Propuesta de layout:

```text
assistant/
  prompts/
    preprocess.yaml          # stable_prompt goals
    conversational_clinical.yaml
    informational_conversational.yaml
    ambiguous_conversational.yaml   # copy de preguntas fijas + reglas mínimas
  copy/
    channel-copy.yaml        # UI por perfil (WhatsApp/app)
  routing/
    intent-families.yaml
    hint-resolution.yaml
    booking-offer.yaml       # prioridad CTA clinical (hoy en conversational-channel)
  intents/                   # flows (sin intent_semantics de oferta a mediano plazo)
```

PHP solo ensambla; un loader por canal.

---

## info_content

| Tema | Decisión |
|------|----------|
| Fuente | `info_content_article` (scope producto → provincia → efector) |
| Respuesta informational | Resolver artículo → **IA con body como fuente** (no dump crudo como única UX) |
| CTA | Campo(s) `intent_id` (o lista) en artículo |
| RBAC admin | Bioenlace: global; provincia/efector: override de `body`; `intent_id`/capabilities globales = producto |
| Visibilidad paciente | Si el artículo tiene intent(s): solo si el usuario puede ejecutar al menos uno; si no, no servir ese artículo (o variante sin CTA institucional) |
| Conversational clinical | **No** llamar `tryResolveFromText` (sacar del canal clinical) |
| `intent_semantics` | Dejar de ser ficha de oferta en YAML; keywords en intent; oferta clinical desde metadata de booking; copy largo en artículo |
| Pre-consulta | Artículo = qué es / para qué; preguntas del pack = care cohort (otro surface) |

---

## Módulo de chat “que entiende más”

| Capacidad | Idea |
|-----------|------|
| Tags de hilo | Cada interacción: `thread_tag` / dominio (`clinical`, `product_help`, `operational`, …) |
| Historial | Prompt y oferta usan historial del **hilo activo**, no “últimos N del usuario” mezclados |
| Desvío | Mensaje actual vs hilo activo → si cambia dominio, no reusar CTA/content del hilo anterior; posible paso por `ambiguous` |
| Certeza | Estado de hilo: hipótesis de necesidad + `confidence`; bajo → preguntar (libre); alto → CTA/flow (“ya sé qué querés”) |
| Preguntas | Libres en clinical/info (con score); en ambiguous pueden ser **predefinidas** para velocidad |

---

## Botones por canal (producto)

- **Clinical:** siempre Solicitar Atención (aunque diga “estoy bien” tras un síntoma propio).
- **Informational:** texto + “si querés, podés continuar con…” + botón(es) al intent del artículo (horarios de centro → crear turno / solicitar atención con efector, etc.).
- **Fuera de alcance** (amigo con fiebre / medicación): descarte breve, **sin** Solicitar Atención.

---

## Ya aplicado (parcial, no cierra el plan)

- Quitado piso `resolveUserGoal` + fallback heurístico preprocess.
- Prompt preprocess con alcance positivo.
- Urgencia sin categoría de alarma (flow → 107 directo).
- `intent_semantics` aligerado (summary/capabilities) en muchos YAML — pendiente migrar/eliminar según fase info_content.
- Fase 01: goals renombrados + alias; `AmbiguousChannel` + YAML; informational sin caer a clinical; clinical sin `tryResolveFromText`; oferta booking solo por mensaje actual.
- Fase 02: prompts en `assistant/prompts/`; routing en `assistant/routing/`; copy en `assistant/copy/`; loader `AssistantMetadataLoader`.
- Fase 03: `intent_ids` en artículos; IA informational anclada; CTA RBAC; match tolerante; seed `pre_consulta`.

## Deuda explícita a resolver en fases

- Limpieza residual `intent_semantics` de oferta en YAML (checklist 03 / fase 05).
- Hilos / desvío / certeza (fase 04).
- Ofertas/botones y limpieza residual (fase 05).
