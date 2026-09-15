# Fase 04 — Migrar agents knobs YAML → Application Policy PHP

## Objetivo

Eliminar `platform/agents/*.yaml` como fuente de política operativa; cada agente lee una policy tipada en el BC dueño.

## Patrón por agente

1. Identificar consumidor actual (`AutonomousAgentMetadata`, agent class).
2. Crear `BC/Application/…/<AgentId>Policy.php` (constantes / estructura tipada equivalente al YAML).
3. Test de paridad: mismos valores efectivos que el YAML (fixture o assert campo a campo).
4. Cablear agent → Policy PHP; quitar lectura YAML.
5. Borrar YAML del agent_id.
6. Actualizar `ProductMetadataPaths::agentFile` / `agentsDir` (deprecar o fallar si se llama).

## Orden sugerido (menor → mayor acoplamiento)

1. Un agent simple (p. ej. integration-retry o uno de flags mínimos).
2. `turno-antinoshow` (referencia del design de perfiles).
3. Resto Scheduling (resolución, advance-offer, async, reserva-triage).
4. Resto Clinical (lab, prescription-rdi, care-followup, post-discharge, internacion-cama).

## Criterio de done

- Carpeta `metadata/bioenlace/platform/agents/` vacía o solo README histórico borrado.
- Ningún `Yaml::parse` de agent knobs en hot path.
- Docs producto agentes: “policy en Application del BC”.

## Nota ops

Si algún umbral debía cambiarse sin deploy, documentar excepción: `params` Yii env-specific **o** dejar un YAML adapter bajo `Infrastructure/Config` con puerto — preferencia del plan: PHP + params solo para `execution_mode` por entorno.