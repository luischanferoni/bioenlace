# Asistente — Visión general

## Qué es

Capa de producto que interpreta mensajes del usuario (texto o audio), clasifica **intents**, guía **flujos multi-paso** y abre **pantallas embebibles** (UI JSON o widgets) en web SPA y apps Flutter.

## Objetivo

- Reducir formularios fijos: el usuario avanza por pasos conversacionales con selección guiada.
- Reutilizar la **misma API v1** que los clientes nativos (sin duplicar reglas en la UI).
- Descubrir acciones permitidas por **RBAC** y catálogo (`action_id` alineado a rutas).

## Actores

| Actor | Canal |
|-------|--------|
| Paciente | App móvil, SPA paciente |
| Profesional / administración | SPA con sesión operativa (efector, servicio) |
| Sistema | Preprocesado de audio, routing, discovery de acciones |

## Alcance

Incluye: envelope de respuesta, YAML de intents/subintents, contrato UI JSON, generación de `flow_manifest`, hints por entidad.

No incluye: lógica clínica pesada (vive en `Scheduling`, `Clinical`, etc.) ni persistencia fuera de los endpoints que cada paso invoca.

## Relacionado

- [design.md](./design.md)
- [flows/YAML_INTENTS_CONTRACT.md](./flows/YAML_INTENTS_CONTRACT.md)
- Dominio turnos (intents de agenda): [turnos/flows/intents-turnos.md](../turnos/flows/intents-turnos.md)
