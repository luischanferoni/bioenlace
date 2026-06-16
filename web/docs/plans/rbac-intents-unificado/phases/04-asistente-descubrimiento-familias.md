# Fase 4 — Asistente: descubrimiento y familias

## Objetivo

Que el asistente resuelva «modificar X» hacia el intent correcto (o pregunte entre variantes) **sin hardcode** de `intent_id` en orquestadores, alineado con reglas `capas-y-metadata-sin-hardcode`.

## Tareas

### 4.1 Metadata familias

- `intent_family` en YAML de cada variante
- Entradas en `intent-classification-rules.yaml` para verbos edit/list/info
- `assistant-shortcuts.yaml`: apuntar a familias o intents concretos según producto

### 4.2 Motor genérico de elección de contexto

- Servicio nuevo o extensión de `SubIntentEngine` / clasificador:
  - Entrada: familia o candidatos NL
  - Filtrar por RBAC (`YamlIntentCatalogService`)
  - Si |candidatos| > 1 → paso `assistant_text` desde `intent_semantics` del YAML (no strings en PHP)
- No enumerar `condicion-laboral` en `ChatOrchestrator`

### 4.3 Resolución de campos NL

- Si mensaje menciona atributo: matcher contra `fields[].keywords` del intent activo
- Si match único → saltar `field_groups`
- Si no match → mensaje estándar «no disponible en esta operación»

### 4.4 `DataAccessUiActionCatalog`

- Sustituir descubrimiento dinámico desde `data-access-config` por índice de intents read/list/edit
- Mantener agnóstico de dominio clínico

### 4.5 Tests

- Clasificación: usuario con un intent vs dos
- Keywords campo vs grupo

## Entregables

- [ ] Flujo conversacional piloto condición laboral end-to-end en asistente
- [ ] Sin `if (intentId === '…')` nuevos en orquestación
- [ ] Tests clasificación / elección

## Dependencias

- Fase 1 (metadata)
- Fase 5 recomendada para E2E piloto

## Estado

Pendiente.
